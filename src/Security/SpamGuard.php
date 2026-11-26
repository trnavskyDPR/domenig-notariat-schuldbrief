<?php

declare(strict_types=1);

namespace Schuldbrief\Security;

use DateTimeImmutable;

/**
 * Spamschutz ohne Drittanbieter (kein reCAPTCHA):
 * - Fallenfeld «website»: für Menschen unsichtbar, Bots füllen es aus.
 * - Mindest-Ausfüllzeit: Das Formular trägt einen signierten Zeitstempel.
 *   Wer schneller als MIN_SECONDS absendet, ist sehr wahrscheinlich ein Bot.
 * Es wird nichts gespeichert.
 */
final class SpamGuard
{
    public const HONEYPOT = 'website';
    public const TIME_FIELD = 'ft';
    private const MIN_SECONDS = 3;
    private const TOKEN_TTL = 172800; // 2 Tage: auch lange offene Formulare bleiben gültig

    public function __construct(private Signer $signer, private DateTimeImmutable $now)
    {
    }

    /** Signierter Zeitstempel für das versteckte Feld im Formular. */
    public function formToken(): string
    {
        return $this->signer->sign(['t' => $this->now->getTimestamp()], self::TOKEN_TTL, $this->now);
    }

    /**
     * @param array<string, mixed> $post
     * @return ?string Textschlüssel des Fehlers oder null, wenn alles in Ordnung ist
     */
    public function check(array $post): ?string
    {
        $honeypot = $post[self::HONEYPOT] ?? '';
        if (!is_string($honeypot) || trim($honeypot) !== '') {
            return 'error.spam';
        }
        $token = $post[self::TIME_FIELD] ?? '';
        $data = is_string($token) ? $this->signer->verify($token, $this->now) : null;
        if (!is_int($data['t'] ?? null)) {
            return 'error.form_expired';
        }
        if ($this->now->getTimestamp() - $data['t'] < self::MIN_SECONDS) {
            return 'error.spam';
        }

        return null;
    }
}
