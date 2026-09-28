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

$user = RequireAuth::check(); // 401, falls nicht eingeloggt — Gesuch braucht einen Autor

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$subjectId = (int) ($input['subject_id'] ?? 0);
$title = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));

if ($subjectId <= 0 || $title === '' || $description === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Fach, Titel und Beschreibung sind erforderlich.']);
    exit;
}

try {
    $repo = new HelpRequestRepository(Database::connection());
    $id = $repo->create((int) $user['sub'], $subjectId, $title, $description);
    http_response_code(201);
    echo json_encode(['id' => $id]);
} catch (Throwable $e) {
    error_log('[Requests:create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
