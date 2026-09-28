<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TutorRepository.php';
require_once __DIR__ . '/../../src/Middleware/RequireAuth.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

$user = RequireAuth::check(); // beendet die Anfrage selbst mit 401, falls nicht eingeloggt

if ($user['role'] !== 'tutor') {
    http_response_code(403);
    echo json_encode(['error' => 'Nur Tutoren haben ein bearbeitbares Profil.']);
    exit;
}

try {
    $repo = new TutorRepository(Database::connection());
    echo json_encode($repo->findOwnProfile((int) $user['sub']));
} catch (Throwable $e) {
    error_log('[Profile:me] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
