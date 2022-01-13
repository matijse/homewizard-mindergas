<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$log = new Logger('app');
$log->pushHandler(new StreamHandler('logs/readings.log', Logger::INFO, false));
$log->pushHandler(new StreamHandler('logs/errors.log', Logger::WARNING, false));

function getReading(): float {
    $client = new Client;
    $url = 'http://'.$_ENV['HOMEWIZARD_IP'].'/api/v1/data';

    $response = $client->get($url);

    $data = json_decode($response->getBody()->getContents());

    return floatval($data->total_gas_m3);
}

function postReading(float $reading, string $date): Response {
    $client = new Client;
    $url = 'https://www.mindergas.nl/api/meter_readings';
    $token = $_ENV['MINDERGAS_TOKEN'];

    $json = [
        'date' => $date,
        'reading' => $reading,
    ];

    return $client->post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'AUTH-TOKEN' => $token,
        ],
        'json' => $json,
    ]);
}

try {
    $total_gas_m3 = getReading();
    $date = date('Y-m-d');
    $log->info('Sending reading: '.$total_gas_m3.' for date '.$date);
    $response = postReading($total_gas_m3, $date);
    if ($response->getStatusCode() !== 201) {
        $log->warning('Received wrong status code: '.$response->getStatusCode().' Content: '.$response->getBody()->getContents());
    }
} catch (Throwable $exception) {
    $log->error($exception->getMessage(), $exception->getTrace());
    return;
}
