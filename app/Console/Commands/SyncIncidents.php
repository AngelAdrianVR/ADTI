<?php

namespace App\Console\Commands;

use App\Models\Payroll;
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

        // obtener token
        $response = $client->post('http://192.168.1.189:81/api-token-auth/', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => 'SuperADTI',
                'password' => 'adti1234',
            ],
        ]);

        // Manejar la respuesta
        if ($response->getStatusCode() == 200) {
            $data = json_decode($response->getBody(), true);

            // Construir la URL con las fechas
            $startDate = today()->startOfDay()->toDateTimeString();
            $endDate = today()->addDay()->startOfDay()->toDateTimeString();
            $url = "http://192.168.1.189:81/iclock/api/transactions/?start_time=$startDate&end_time=$endDate";

            // Realizar la solicitud con el token
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Token ' . $data['token'],
                ],
            ]);

            // Manejar la respuesta del segundo endpoint
            if ($response->getStatusCode() == 200) {
                $transactions = json_decode($response->getBody(), true);
                // Procesar los datos de las transacciones
                // foreach ($transactions as $transaction) {
                //     // Hacer algo con cada transacción, por ejemplo, guardar en la base de datos
                //     // ...
                // }
                Log::info("inicio: $startDate, Fin: $endDate");
            } else {
                Log::error('Error al obtener las transacciones. Código de estado: ' . $response->getStatusCode());
            }
        } else {
            Log::error('Error al consumir el endpoint /iclock/api/transactions/. Código de estado: ' . $response->getStatusCode());
        }
    }
}
