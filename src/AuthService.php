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
        private string $digikabuSharedSecret,
        private string $tutorioSecret,
        private int $tutorioTtl
    ) {
    }

    public function login(string $email, string $password): array
    {
        $digikabuJwt = $this->requestDigikabuToken($email, $password);

        // Schritt: Digikabu-JWT NICHT blind vertrauen — Signatur und exp
        // prüfen, bevor der Payload verwendet wird.
        $payload = Jwt::verifyAndDecode($digikabuJwt, $this->digikabuSharedSecret);

        $name = $payload['name'] ?? throw new UnexpectedValueException('Digikabu-Token ohne "name".');
        $role = $this->mapRole($payload['role'] ?? throw new UnexpectedValueException('Digikabu-Token ohne "role".'));

        $userId = $this->findOrCreateUser($email, $name, $role);

        $tutorioPayload = [
            'sub' => $userId,
            'name' => $name,
            'role' => $role,
        ];

        $token = Jwt::encode($tutorioPayload, $this->tutorioSecret, $this->tutorioTtl);

        return [
            'token' => $token,
            'user' => ['id' => $userId, 'name' => $name, 'role' => $role],
        ];
    }

    private function requestDigikabuToken(string $email, string $password): string
    {
        // Nur für lokales Testen ohne echtes Digikabu (siehe .env: AUTH_STUB_MODE).
        // WICHTIG: vor dem echten Einsatz AUTH_STUB_MODE aus der .env entfernen!
        if (getenv('AUTH_STUB_MODE') === 'true') {
            return $this->stubDigikabuToken($email, $password);
        }

        $ch = curl_init($this->digikabuBaseUrl . '/api/authenticate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['username' => $email, 'password' => $password]),
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            throw new DigikabuAuthException('Anmeldung bei Digikabu fehlgeschlagen.');
        }

        $data = json_decode($response, true);
        $token = $data['token'] ?? null;

        if (!is_string($token)) {
            throw new DigikabuAuthException('Digikabu-Antwort enthielt kein Token.');
        }

        return $token;
    }

    /**
     * Simuliert eine Digikabu-Antwort: erzeugt ein JWT, so wie Digikabu es
     * schicken würde, und signiert es korrekt mit dem gemeinsamen Secret —
     * damit auch die Signaturprüfung in login() echt getestet wird.
     * Passwort "tutor" -> Rolle Tutor, alles andere -> Rolle Schüler.
     */
    private function stubDigikabuToken(string $email, string $password): string
    {
        $role = ($password === 'tutor') ? 'Tutor' : 'Schueler';
        $name = explode('@', $email)[0];

        return Jwt::encode(['name' => $name, 'role' => $role], $this->digikabuSharedSecret, 3600);
    }

    private function mapRole(string $digikabuRole): string
    {
        return strtolower($digikabuRole) === 'tutor' ? 'tutor' : 'suchend';
    }

    private function findOrCreateUser(string $email, string $name, string $role): int
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE school_email = :email');
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare('
            INSERT INTO users (school_email, display_name, role) VALUES (:email, :name, :role)
        ');
        $stmt->execute(['email' => $email, 'name' => $name, 'role' => $role]);

        return (int) $this->db->lastInsertId();
    }
}
