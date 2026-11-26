<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

/**
 * Health-Check unter /metrics (Atoll erwartet Port 8000, Pfad /metrics, Status 200).
 *
 * Bewusst minimal: keine Konfiguration, kein Graph, keine Eingaben, kein Log.
 * Das Format ist Prometheus-kompatibel (Textformat); die Zeile «# OK» dient Menschen.
 */
final class Health
{
    public const PATH = '/metrics';

    public static function matches(string $path): bool
    {
        return rtrim($path, '/') === self::PATH;
    }

    public static function response(): Response
    {
        $body = "# OK\n"
            . "# HELP notariat_schuldbrief_up Die Anwendung läuft (1 = ja).\n"
            . "# TYPE notariat_schuldbrief_up gauge\n"
            . "notariat_schuldbrief_up 1\n";

        return Response::text($body);
    }
}
