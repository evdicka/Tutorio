<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/TutorRepository.php';
require_once __DIR__ . '/../../src/Middleware/RequireAuth.php';

loadEnv(__DIR__ . '/../../.env');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

$user = RequireAuth::check();

if ($user['role'] !== 'tutor') {
    http_response_code(403);
    echo json_encode(['error' => 'Nur Tutoren können ein Profil speichern.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$description = trim((string) ($input['description'] ?? ''));
$availability = trim((string) ($input['availability'] ?? ''));
$priceModel = mb_substr(trim((string) ($input['price_model'] ?? '')), 0, 255);
$subjectIds = array_map('intval', $input['subject_ids'] ?? []);

if ($description === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte eine kurze Beschreibung angeben.']);
    exit;
}

if ($priceModel === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte ein Preismodell angeben (z. B. "kostenlos").']);
    exit;
}

if (count($subjectIds) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte mindestens ein Fach auswählen.']);
    exit;
}

try {
    $repo = new TutorRepository(Database::connection());
    $repo->upsertProfile((int) $user['sub'], $description, $availability, $priceModel, $subjectIds);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('[Profile:update] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Interner Fehler. Bitte später erneut versuchen.']);
}
