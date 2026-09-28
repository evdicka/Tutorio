<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/HelpRequestRepository.php';
require_once __DIR__ . '/../../src/Middleware/RequireAuth.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

$user = RequireAuth::check();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$requestId = (int) ($input['request_id'] ?? 0);
$content = trim((string) ($input['content'] ?? ''));

if ($content === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Antwort darf nicht leer sein.']);
    exit;
}

try {
    $repo = new HelpRequestRepository(Database::connection());

    if (!$repo->exists($requestId)) {
        http_response_code(404);
        echo json_encode(['error' => 'Gesuch nicht gefunden.']);
        exit;
    }

    $repo->addReply($requestId, (int) $user['sub'], $content);
    http_response_code(201);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[Requests:reply] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
