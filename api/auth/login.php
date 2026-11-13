<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/AuthService.php';

loadEnv(__DIR__ . '/../../.env');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Benutzername und Passwort erforderlich.']);
    exit;
}

try {
     $service = new AuthService(
        Database::connection(),
        getenv('DIGIKABU_BASE_URL'),
        getenv('TUTORIO_JWT_SECRET'),
        (int) getenv('TUTORIO_JWT_TTL')
    );

    $result = $service->login($username, $password);

    setcookie('tutorio_token', $result['token'], [
        'expires' => time() + (int) getenv('TUTORIO_JWT_TTL'),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    echo json_encode(['user' => $result['user']]);
} catch (DigikabuAuthException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Benutzername oder Passwort falsch.']);
} catch (Throwable $e) {
    error_log('[Auth:login] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler. Bitte später erneut versuchen.']);
}
