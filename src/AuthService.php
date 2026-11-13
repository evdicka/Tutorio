<?php

declare(strict_types=1);

require_once __DIR__ . '/Jwt.php';

final class DigikabuAuthException extends RuntimeException
{
}

final class AuthService
{
    public function __construct(
        private PDO $db,
        private string $digikabuBaseUrl,
        private string $tutorioSecret,
        private int $tutorioTtl
    ) {
    }

    public function login(string $username, string $password): array
    {
        $digikabuJwt = $this->requestDigikabuToken($username, $password);

        // Schlüssel von Digikabu ist unbekannt -> Signatur kann nicht geprüft werden.
        // Das Token kommt direkt von Digikabu (HTTPS, Backend -> Backend), nicht vom Client.
        $payload = Jwt::decodeUnverified($digikabuJwt);

        $userlevel = (string) ($payload['userlevel'] ?? '');
        $klasse = isset($payload['klasse']) ? (string) $payload['klasse'] : null;

        $user = $this->findOrCreateUser($username, $this->mapRole($userlevel));

        // Eigenes JWT mit Daten aus Digikabu; das Digikabu-JWT wird verworfen.
        $token = Jwt::encode([
            'sub' => $user['id'],
            'name' => $username,
            'role' => $user['role'],
            'userlevel' => $userlevel,
            'klasse' => $klasse,
        ], $this->tutorioSecret, $this->tutorioTtl);

        return [
            'token' => $token,
            'user' => ['id' => $user['id'], 'name' => $username, 'role' => $user['role']],
        ];
    }

    private function requestDigikabuToken(string $username, string $password): string
    {
        // Nur für lokales Testen ohne echtes Digikabu (.env: AUTH_STUB_MODE=true).
        if (getenv('AUTH_STUB_MODE') === 'true') {
            return $this->stubDigikabuToken($username, $password);
        }

        $ch = curl_init(rtrim($this->digikabuBaseUrl, '/') . '/api/authenticate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['userName' => $username, 'password' => $password]),
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            error_log('[Digikabu] HTTP ' . $status . ' ' . $curlError);
            throw new DigikabuAuthException('Anmeldung bei Digikabu fehlgeschlagen.');
        }

        // Digikabu antwortet laut Konzept mit dem nackten JWT; falls es doch
        // als JSON {"token": "..."} kommt, wird das ebenfalls akzeptiert.
        $body = trim((string) $response);
        $token = str_starts_with($body, '{')
            ? (json_decode($body, true)['token'] ?? null)
            : trim($body, "\" \t\n\r");

        if (!is_string($token) || substr_count($token, '.') !== 2) {
            error_log('[Digikabu] Unerwartetes Antwortformat.');
            throw new DigikabuAuthException('Digikabu-Antwort enthielt kein gültiges Token.');
        }

        return $token;
    }

    /**
     * Simuliert eine Digikabu-Antwort mit dem Payload-Aufbau aus dem Konzept.
     * Passwort "tutor" -> userlevel "Tutor", alles andere -> "Schueler".
     */
    private function stubDigikabuToken(string $username, string $password): string
    {
        return Jwt::encode([
            'userlevel' => ($password === 'tutor') ? 'Tutor' : 'Schueler',
            'klasse' => 'BFI11A',
            'nbf' => time(),
        ], 'stub-secret', 3600);
    }

    private function mapRole(string $userlevel): string
    {
        return strtolower($userlevel) === 'tutor' ? 'tutor' : 'suchend';
    }

    /**
     * Neuer Nutzer: Rolle aus Digikabu-userlevel. Bestehender Nutzer: Rolle aus
     * der Datenbank (so kann ein Schüler dort manuell zum Tutor gemacht werden,
     * da Digikabu keinen Tutor-Level kennt).
     */
    private function findOrCreateUser(string $username, string $role): array
    {
        $stmt = $this->db->prepare('SELECT id, role FROM users WHERE school_email = :username');
        $stmt->execute(['username' => $username]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            return ['id' => (int) $existing['id'], 'role' => $existing['role']];
        }

        $stmt = $this->db->prepare('
            INSERT INTO users (school_email, display_name, role) VALUES (:username, :name, :role)
        ');
        $stmt->execute(['username' => $username, 'name' => $username, 'role' => $role]);

        return ['id' => (int) $this->db->lastInsertId(), 'role' => $role];
    }
}
