<?php

declare(strict_types=1);

namespace Schuldbrief;

/**
 * Konfiguration aus Umgebungsvariablen bzw. .env.
 * Echte Umgebungsvariablen haben Vorrang vor der .env-Datei.
 * Neue Variablen immer auch in .env.example und README dokumentieren.
 */
final class Config
{
    public const MODES = ['mock', 'test', 'live'];

    /** Nur für den Mock-Modus: lokale Entwicklung ohne .env. */
    private const MOCK_SECRET = 'mock-modus-unsicherer-schluessel-nur-lokal-0000';

    private const DEFAULTS = [
        'APP_MODE' => 'mock',
        'UPLOAD_MAX_MB' => '5',
        'UPLOAD_MAX_FILES' => '6',
        'CONTACT_PHONE' => '+41 58 531 20 00',
        'CONTACT_EMAIL' => 'notariat@domenig.law',
        'OFFICE_ADDRESS' => 'Laupenstrasse 1, 3008 Bern',
        'PRIVACY_URL_DE' => 'https://www.domenig.law/datenschutz',
        'TEST_MAILBOX' => 'test-notariat@domenig.law',
        'GRAPH_TIMEOUT_SECONDS' => '10',
        'SENDER_MAILBOX' => 'notariat@domenig.law',
        'INTERNAL_RECIPIENTS' => 'info@domenig.law,notariat@domenig.law,schneider@domenig.law',
        'ID_COPY_RECIPIENTS' => 'info@domenig.law,notariat@domenig.law,schneider@domenig.law',
    ];

    /** @param array<string, string> $vars */
    private function __construct(private array $vars, private bool $useProcessEnv)
    {
    }

    public static function load(string $root): self
    {
        return new self(Env::parseFile($root . '/.env'), true);
    }

    /** Für Tests: nur die übergebenen Werte, keine Prozess-Umgebung. */
    public static function fromArray(array $vars): self
    {
        return new self(array_map('strval', $vars), false);
    }

    public function get(string $key): string
    {
        if ($this->useProcessEnv) {
            $real = getenv($key);
            if ($real !== false && $real !== '') {
                return $real;
            }
        }
        $value = $this->vars[$key] ?? '';

        return $value !== '' ? $value : (self::DEFAULTS[$key] ?? '');
    }

    /** mock = nichts wird gesendet · test = alles ans Testpostfach · live = produktiv */
    public function mode(): string
    {
        $mode = strtolower($this->get('APP_MODE'));
        if (!in_array($mode, self::MODES, true)) {
            throw new ConfigException('APP_MODE muss mock, test oder live sein.');
        }

        return $mode;
    }

    public function isMock(): bool
    {
        return $this->mode() === 'mock';
    }

    /** Schlüssel für signierte Links und Formular-Zeitstempel. In test/live Pflicht. */
    public function secret(): string
    {
        $secret = $this->get('APP_SECRET');
        if ($secret === '' && $this->isMock()) {
            return self::MOCK_SECRET;
        }
        if (strlen($secret) < 32) {
            throw new ConfigException('APP_SECRET fehlt oder ist kürzer als 32 Zeichen.');
        }

        return $secret;
    }

    /** Höchste Grösse pro hochgeladener Datei (Ausweiskopie) in Bytes. */
    public function uploadMaxBytes(): int
    {
        return max(1, $this->int('UPLOAD_MAX_MB')) * 1048576;
    }

    /** Höchste Anzahl Dateien (mehrere Grundeigentümer, je Vorder- und Rückseite). */
    public function uploadMaxFiles(): int
    {
        return max(1, $this->int('UPLOAD_MAX_FILES'));
    }

    /**
     * Zugangsdaten der Entra-ID-App «Schuldbriefbestellung (Atoll)». Der geheime Schlüssel kommt
     * nur aus der Umgebung (Atoll-Variablen bzw. .env auf dem Server) – nie aus dem Code.
     *
     * @return array{0: string, 1: string, 2: string} Tenant-ID, Client-ID, Client-Secret
     */
    public function graphCredentials(): array
    {
        $tenant = $this->get('GRAPH_TENANT_ID');
        $client = $this->get('GRAPH_CLIENT_ID');
        $secret = $this->get('GRAPH_CLIENT_SECRET');
        $guid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($guid, $tenant) || !preg_match($guid, $client) || $secret === '') {
            throw new ConfigException('GRAPH_TENANT_ID, GRAPH_CLIENT_ID und GRAPH_CLIENT_SECRET müssen gesetzt sein.');
        }

        return [$tenant, $client, $secret];
    }

    /** Wartezeit auf Microsoft in Sekunden (3–30). */
    public function graphTimeout(): int
    {
        return max(3, min(30, $this->int('GRAPH_TIMEOUT_SECONDS')));
    }

    /** Absender aller Mails. Im Testmodus das Testpostfach. */
    public function senderMailbox(): string
    {
        $key = $this->mode() === 'test' ? 'TEST_MAILBOX' : 'SENDER_MAILBOX';
        $mailbox = $this->get($key);
        if (filter_var($mailbox, FILTER_VALIDATE_EMAIL) === false) {
            throw new ConfigException($key . ' ist keine gültige Adresse.');
        }

        return $mailbox;
    }

    /**
     * Wer bei jedem Antrag eine Mail erhält – jede Adresse ein eigenes Exemplar im eigenen Posteingang.
     * Im Testmodus nur das Testpostfach.
     *
     * @return list<string>
     */
    public function internalRecipients(): array
    {
        return $this->mode() === 'test' ? [$this->get('TEST_MAILBOX')] : $this->addresses('INTERNAL_RECIPIENTS');
    }

    /**
     * Wer davon die Ausweiskopien als Anhang erhält (Teilmenge von INTERNAL_RECIPIENTS).
     * Standard: alle drei (Entscheid Trevor Trnavsky, 26.11.2026).
     *
     * @return list<string>
     */
    public function idCopyRecipients(): array
    {
        return $this->mode() === 'test' ? [$this->get('TEST_MAILBOX')] : $this->addresses('ID_COPY_RECIPIENTS');
    }

    /** Empfänger der Eingangsbestätigung: die Bank – im Testmodus stattdessen das Testpostfach. */
    public function bankRecipient(string $bankEmail): string
    {
        return $this->mode() === 'test' ? $this->get('TEST_MAILBOX') : $bankEmail;
    }

    public function contactPhone(): string
    {
        return $this->get('CONTACT_PHONE');
    }

    public function contactEmail(): string
    {
        return $this->get('CONTACT_EMAIL');
    }

    public function officeAddress(): string
    {
        return $this->get('OFFICE_ADDRESS');
    }

    /** Datenschutzerklärung je Sprache; fehlt eine Sprache, gilt die deutsche Fassung. */
    public function privacyUrl(string $lang): string
    {
        $url = $this->get('PRIVACY_URL_' . strtoupper($lang));

        return $url !== '' ? $url : $this->get('PRIVACY_URL_DE');
    }

    /** Kommagetrennte Adressliste, klein geschrieben und ohne Doppel. @return list<string> */
    private function addresses(string $key): array
    {
        $list = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($this->get($key)))))));
        foreach ($list as $mail) {
            if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
                throw new ConfigException($key . ' enthält eine ungültige Adresse.');
            }
        }
        if ($list === []) {
            throw new ConfigException($key . ' ist leer.');
        }

        return $list;
    }

    private function int(string $key): int
    {
        $value = $this->get($key);
        if (!preg_match('/^\d+$/', $value)) {
            throw new ConfigException($key . ' muss eine ganze Zahl sein.');
        }

        return (int) $value;
    }
}
