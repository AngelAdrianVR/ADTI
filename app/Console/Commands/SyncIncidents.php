<?php

namespace App\Console\Commands;

use App\Models\BioTimeTransactions;
use App\Models\Payroll;
use App\Models\PayrollUser;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncIncidents extends Command
{
    protected $signature = 'payrolls:sync-incidents';
    protected $description = 'Sincronizar registros de asistencia desde BioTime Pro';

    public function handle()
    {
        $localIp = $this->getLocalIp();
        $client = new Client();

        try {
            // Obtener token
            $response = $client->post("http://$localIp:81/api-token-auth/", [
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
                $url = "http://$localIp:81/iclock/api/transactions/?start_time=$startDate&end_time=$endDate";

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
                    $response = Http::get('https://app.adti.com.mx/api/get-todays-transactions/');

                    if ($countFromApi > $response['transactions']) {
                        $newRecords = array_slice($transactions['data'], $response['transactions']);

                        // procesar transaccion en BDD de produccion desde API
                        foreach ($newRecords as $record) {
                            $time = $record['punch_time'];
                            $emp_code = $record['emp_code'];
                            $url = 'https://app.adti.com.mx/api/process-transaction/' . urlencode($time) . '/' . $emp_code;
                            $response = Http::get($url);
                        }

                        Log::info(count($newRecords) . " transacciones procesadas.");
                    } else {
                        Log::info("No se encontraron nuevas transacciones.");
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

    private function getLocalIp()
    {
        // Ejecuta el comando ipconfig y captura la salida
        $output = shell_exec('ipconfig');

        // Busca las líneas con la IPv4 local
        preg_match('/IPv4.*?:\s*([0-9.]+)/', $output, $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        } else {
            Log::error('No se pudo obtener Ipv4. Revisar funcion getLocalIP desde el comando de SyncIncidents.php');
            return '0.0.0.0';
        }
    }
}
