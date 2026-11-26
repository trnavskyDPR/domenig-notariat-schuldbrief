<?php

declare(strict_types=1);

namespace Schuldbrief\Security;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Signierte, ablaufende Tokens (HMAC-SHA256). Damit braucht die Bestätigungsseite
 * keine Session und keinen Speicher. Inhalt nur nur Sprache und Versandstatus – keine Antragsdaten.
 */
final class Signer
{
    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Schlüssel zu kurz.');
        }
    }

    /** @param array<string, string|int> $data */
    public function sign(array $data, int $ttlSeconds, DateTimeImmutable $now): string
    {
        $data['exp'] = $now->getTimestamp() + $ttlSeconds;
        $payload = self::b64(json_encode($data, JSON_THROW_ON_ERROR));

        return $payload . '.' . self::b64(hash_hmac('sha256', $payload, $this->secret, true));
    }

    /** @return array<string, mixed>|null */
    public function verify(string $token, DateTimeImmutable $now): ?array
    {
        if (strlen($token) > 512 || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $mac] = explode('.', $token);
        $expected = self::b64(hash_hmac('sha256', $payload, $this->secret, true));
        if (!hash_equals($expected, $mac)) {
            return null;
        }
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data) || !is_int($data['exp'] ?? null) || $data['exp'] < $now->getTimestamp()) {
            return null;
        }

        return $data;
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
