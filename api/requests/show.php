<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/HelpRequestRepository.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige ID.']);
    exit;
}

try {
    $repo = new HelpRequestRepository(Database::connection());
    $request = $repo->findById($id);

    if ($request === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Gesuch nicht gefunden.']);
        exit;
    }

    echo json_encode($request);
} catch (Throwable $e) {
    error_log('[Requests:show] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
