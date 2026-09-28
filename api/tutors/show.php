<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TutorRepository.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

$tutorId = (int) ($_GET['id'] ?? 0);

if ($tutorId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Tutor-ID.']);
    exit;
}

try {
    $repo = new TutorRepository(Database::connection());
    $tutor = $repo->findPublicById($tutorId);

    if ($tutor === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Tutor nicht gefunden.']);
        exit;
    }

    echo json_encode($tutor);
} catch (Throwable $e) {
    error_log('[Tutors:show] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
