<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/SubjectRepository.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

try {
    $repo = new SubjectRepository(Database::connection());
    echo json_encode($repo->all());
} catch (Throwable $e) {
    error_log('[Subjects:list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
