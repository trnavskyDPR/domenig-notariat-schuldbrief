<?php

declare(strict_types=1);

namespace Schuldbrief;

/**
 * Minimaler .env-Leser (KEY=VALUE, Kommentare mit #, optionale Anführungszeichen).
 * Bewusst ohne Abhängigkeit, damit auf dem Server kein Composer nötig ist.
 */
final class Env
{
    /** @return array<string, string> */
    public static function parseFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $vars = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $quote = $value[0] ?? '';
            if (strlen($value) >= 2 && ($quote === '"' || $quote === "'") && $value[-1] === $quote) {
                $value = substr($value, 1, -1);
            } else {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }
            $vars[$key] = $value;
        }

        return $vars;
    }
}
