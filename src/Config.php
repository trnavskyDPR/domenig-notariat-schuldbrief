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
        'CONTACT_PHONE' => '+41 58 531 20 00',
        'CONTACT_EMAIL' => 'notariat@domenig.law',
        'OFFICE_ADDRESS' => 'Laupenstrasse 1, 3008 Bern',
        'PRIVACY_URL_DE' => 'https://www.domenig.law/datenschutz',
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
}
