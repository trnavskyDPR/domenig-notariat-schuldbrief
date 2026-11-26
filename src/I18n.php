<?php

declare(strict_types=1);

namespace Schuldbrief;

use RuntimeException;

/** Texte aus lang/<sprache>.php. Fehlende Schlüssel fallen auf Deutsch zurück. */
final class I18n
{
    public const LANGS = ['de', 'fr', 'en'];
    public const DEFAULT = 'de';

    /** @var array<string, mixed> */
    private array $strings;
    /** @var array<string, mixed> */
    private array $fallback;

    public function __construct(public readonly string $lang, string $dir)
    {
        if (!in_array($lang, self::LANGS, true)) {
            throw new RuntimeException('Unbekannte Sprache.');
        }
        $this->fallback = require $dir . '/' . self::DEFAULT . '.php';
        $this->strings = $lang === self::DEFAULT ? $this->fallback : require $dir . '/' . $lang . '.php';
    }

    /** Sprache aus URL-Parameter oder Formular; alles Unbekannte ergibt Deutsch. */
    public static function pick(mixed $requested): string
    {
        $lang = is_string($requested) ? strtolower(trim($requested)) : '';

        return in_array($lang, self::LANGS, true) ? $lang : self::DEFAULT;
    }

    /** @param array<string, string|int> $params */
    public function t(string $key, array $params = []): string
    {
        $text = $this->strings[$key] ?? $this->fallback[$key] ?? $key;
        if (!is_string($text)) {
            return $key;
        }
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->strings);
    }

    public function htmlLang(): string
    {
        return $this->t('meta.html_lang');
    }
}
