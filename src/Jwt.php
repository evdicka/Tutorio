<?php

/**
 * Minimalistische JWT-Implementierung für HS256.
 * Bewusst ohne externe Bibliothek, passend zu ADR-003.
 */
final class Jwt
{
    public static function encode(array $payload, string $secret, int $ttlSeconds): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = self::sign("{$headerEncoded}.{$payloadEncoded}", $secret);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    public static function verifyAndDecode(string $token, string $secret): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Ungültiges JWT-Format.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $expectedSignature = self::sign("{$headerEncoded}.{$payloadEncoded}", $secret);
        $actualSignature = self::base64UrlDecode($signatureEncoded);

        if (!hash_equals($expectedSignature, $actualSignature)) {
            throw new UnexpectedValueException('Ungültige JWT-Signatur.');
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new UnexpectedValueException('JWT ist abgelaufen.');
        }

        return $payload;
    }

    /**
     * Dekodiert ein JWT OHNE Signaturprüfung (Schlüssel von Digikabu unbekannt).
     * Nur für Tokens verwenden, die direkt von Digikabu per HTTPS kommen,
     * niemals für Tokens vom Client.
     */
    public static function decodeUnverified(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Ungültiges JWT-Format.');
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true, 512, JSON_THROW_ON_ERROR);

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new UnexpectedValueException('JWT ist abgelaufen.');
        }

        return $payload;
    }

    private static function sign(string $data, string $secret): string
    {
        return hash_hmac('sha256', $data, $secret, true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
