<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/HelpRequestRepository.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

$subjectId = (isset($_GET['subject_id']) && $_GET['subject_id'] !== '')
    ? (int) $_GET['subject_id']
    : null;

try {
    $repo = new HelpRequestRepository(Database::connection());
    echo json_encode($repo->list($subjectId));
} catch (Throwable $e) {
    error_log('[Requests:list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
