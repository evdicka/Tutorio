<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RatingRepository.php';
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
$tutorId = (int) ($input['tutor_id'] ?? 0);
$stars = (int) ($input['stars'] ?? 0);
$comment = isset($input['comment']) ? trim((string) $input['comment']) : null;
$comment = ($comment === '') ? null : $comment;

if ($stars < 1 || $stars > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Bewertung muss zwischen 1 und 5 Sternen liegen.']);
    exit;
}

if ($tutorId === (int) $user['sub']) {
    http_response_code(400);
    echo json_encode(['error' => 'Du kannst dich nicht selbst bewerten.']);
    exit;
}

try {
    $repo = new RatingRepository(Database::connection());

    if (!$repo->tutorExists($tutorId)) {
        http_response_code(404);
        echo json_encode(['error' => 'Tutor nicht gefunden.']);
        exit;
    }

    if ($repo->hasRated($tutorId, (int) $user['sub'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Du hast diesen Tutor bereits bewertet.']);
        exit;
    }

    $repo->create($tutorId, (int) $user['sub'], $stars, $comment);
    http_response_code(201);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[Ratings:create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
