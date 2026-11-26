<?php

declare(strict_types=1);

use Schuldbrief\App;
use Schuldbrief\Config;
use Schuldbrief\Http\Health;
use Schuldbrief\Http\Response;

require dirname(__DIR__) . '/src/bootstrap.php';

// Fehler nie im Browser anzeigen und nie Eingaben loggen.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Health-Check für Atoll: ohne Konfiguration, ohne Graph, ohne Daten.
if (Health::matches((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
    Health::response()->send($method !== 'HEAD');
    return;
}

try {
    $config = Config::load(APP_ROOT);
    $app = new App($config, APP_ROOT);
    $response = $app->handle($method, $_GET, $_POST);
} catch (Throwable $e) {
    error_log('Start fehlgeschlagen: ' . App::logLine($e));
    $response = Response::html(
        '<!doctype html><html lang="de"><meta charset="utf-8"><title>Fehler</title>'
        . '<p>Das Antragsformular ist im Moment nicht verfügbar. Bitte rufen Sie uns an: +41 31 380 11 00</p>',
        503,
    );
}

// HTTPS ist Pflicht: Browser merken sich das ein Jahr lang (HSTS). Das TLS-Zertifikat stellt Atoll.
if (($_SERVER['HTTPS'] ?? '') === 'on' || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
    $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000');
}

$response->send($method !== 'HEAD');
