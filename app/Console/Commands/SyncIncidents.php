<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncIncidents extends Command
{
    protected $signature = 'payrolls:sync-incidents';
    protected $description = 'Sincronizar registros de asistencia desde BioTime Pro';

    public function handle()
    {
        $client = new Client();

        try {
            // Obtener token
            $response = $client->post('http://192.168.1.189:81/api-token-auth/', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => 'SuperADTI',
                    'password' => 'adti1234',
                ],
            ]);

            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody(), true);
                $token = $data['token'];

                // Construir la URL con las fechas
                $startDate = today()->startOfDay()->toDateTimeString();
                $endDate = today()->addDay()->startOfDay()->toDateTimeString();
                $url = "http://192.168.1.189:81/iclock/api/transactions/?start_time=$startDate&end_time=$endDate";

                // Realizar la solicitud con el token
                $response = $client->get($url, [
                    'headers' => [
                        'Authorization' => 'Token ' . $token,
                    ],
                ]);

                if ($response->getStatusCode() == 200) {
                    $transactions = json_decode($response->getBody(), true);

                    // Comparar la cantidad de registros
                    $countFromApi = $transactions['count'];
                    $countInDatabase = PayrollUser::whereDate('date', today()->toDateString())->count();

                    if ($countFromApi > $countInDatabase) {
                        $newRecords = array_slice($transactions['data'], $countInDatabase);

                        foreach ($newRecords as $record) {
                            // Identificar si es entrada o salida
                            $employee = User::firstWhere('code', $record['emp_code']);
                            $currentPayroll = Payroll::firstWhere('is_active', true);

                            $existingEntry = PayrollUser::where('user_id', $employee->id)
                                ->where('payroll_id', $currentPayroll->id)
                                ->whereDate('date', today()->toDateString())
                                ->first();

                            $punchTime = Carbon::parse($record['punch_time'])->format('H:i');
                            if (!$existingEntry) { //No existe registro de asistencia del empleado en cuestion
                                PayrollUser::create([
                                    'emp_code' => $record['emp_code'],
                                    'date' => today()->toDateString(),
                                    'check_in' => $punchTime,
                                    'user_id' => $employee->id,
                                    'payroll_id' => $currentPayroll->id,
                                ]);
                            } else { //Ya existe registro de asistencia
                                if (strtotime($punchTime) <= strtotime('17:49')) {
                                    $employee->setPause();
                                } else {
                                    $existingEntry->update([
                                        'check_out' => $punchTime,
                                    ]);
                                }
                            }

                            // Calcular tiempo extra y retardo
                            $currentPayroll->calculateLate();
                            $currentPayroll->calculateExtraTime();
                        }

                        Log::info(count($newRecords) . " nuevos registros agregados a la base de datos.");
                    } else {
                        Log::info("No se encontraron nuevos registros de asistencia.");
                    }
                } else {
                    Log::error('Error al obtener las transacciones. Código de estado: ' . $response->getStatusCode());
                }
            } else {
                Log::error('Error al obtener el token. Código de estado: ' . $response->getStatusCode());
            }
        } catch (Exception $e) {
            Log::error('Ocurrió un error: ' . $e->getMessage());
        }
    }
}
