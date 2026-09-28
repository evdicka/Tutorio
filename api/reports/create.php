<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/ReportRepository.php';
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
$reportedUserId = (int) ($input['reported_user_id'] ?? 0);
$reason = trim((string) ($input['reason'] ?? ''));

if ($reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte einen Grund angeben.']);
    exit;
}

if ($reportedUserId === (int) $user['sub']) {
    http_response_code(400);
    echo json_encode(['error' => 'Du kannst dich nicht selbst melden.']);
    exit;
}

try {
    $repo = new ReportRepository(Database::connection());

    if (!$repo->userExists($reportedUserId)) {
        http_response_code(404);
        echo json_encode(['error' => 'Nutzer nicht gefunden.']);
        exit;
    }

    $repo->create((int) $user['sub'], $reportedUserId, $reason);
    http_response_code(201);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[Reports:create] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler.']);
}
