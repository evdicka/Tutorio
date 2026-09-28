<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TutorRepository.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

// Freitextsuche (Name oder Fach) und Fach-Filter sind beide optional.
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
$subjectId = (isset($_GET['subject_id']) && $_GET['subject_id'] !== '')
    ? (int) $_GET['subject_id']
    : null;

try {
    $repo = new TutorRepository(Database::connection());
    echo json_encode($repo->search($query, $subjectId));
} catch (Throwable $e) {
    error_log('[Tutors:list] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
