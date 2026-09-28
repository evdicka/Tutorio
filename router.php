<?php
// Nur für den lokalen PHP-Built-in-Server (php -S ... router.php).
// Auf einem echten Apache/Nginx-Server wird diese Datei NICHT gebraucht.
//
// Wichtig: Dieser Router liefert statische Dateien SELBST aus (liest sie
// und gibt sie mit dem richtigen Content-Type aus), statt "return false"
// zu nutzen. Grund: bei "return false" sucht der eingebaute Server die
// Datei relativ zum aktuellen Arbeitsverzeichnis (von wo aus "php -S"
// gestartet wurde), NICHT relativ zu diesem router.php. Startet man den
// Server aus einem anderen Ordner (z. B. über die XAMPP-Shell, die immer
// in xampp\ startet), findet er die Dateien dann nicht -> "Not Found".
// Mit expliziter Auslieferung ist der Router unabhängig vom Startordner.

$projectRoot = __DIR__;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// /api/... an den echten api/-Ordner weiterleiten.
if (str_starts_with($uri, '/api/')) {
    $script = $projectRoot . $uri;
    if (file_exists($script)) {
        chdir(dirname($script)); // relative require_once-Pfade im Skript funktionieren so unabhängig vom Startordner
        require $script;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'API-Endpunkt nicht gefunden.']);
    return true;
}

// Root-Aufruf -> Startseite.
if ($uri === '/' || $uri === '') {
    $uri = '/index.html';
}

$publicFile = $projectRoot . '/public' . $uri;
$realPublicDir = realpath($projectRoot . '/public');
$realRequested = realpath($publicFile);

// Sicherheitscheck: verhindert, dass über ../.. Dateien außerhalb von public/ gelesen werden.
if ($realRequested === false || !str_starts_with($realRequested, $realPublicDir)) {
    http_response_code(404);
    echo 'Nicht gefunden.';
    return true;
}

if (is_dir($realRequested)) {
    http_response_code(404);
    echo 'Nicht gefunden.';
    return true;
}

// Content-Type anhand der Dateiendung setzen.
$extension = strtolower(pathinfo($realRequested, PATHINFO_EXTENSION));
$contentTypes = [
    'html' => 'text/html; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
];
header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));

readfile($realRequested);
return true;
