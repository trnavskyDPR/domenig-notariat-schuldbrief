<?php

declare(strict_types=1);

namespace Schuldbrief\Graph;

use RuntimeException;

/**
 * Fehler von Microsoft Graph. Enthält nur HTTP-Status und Microsofts Fehlercode
 * (z. B. «ErrorAccessDenied», «invalid_client») – nie Antragsdaten.
 * Der Code darf deshalb ins Log, damit sich Probleme bei der Einrichtung finden lassen.
 */
final class GraphException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly string $errorCode = '')
    {
        parent::__construct($message);
    }

    /** Fehlercode aus einer Graph- oder Login-Antwort, auf harmlose Zeichen beschränkt. */
    public static function codeFrom(string $body): string
    {
        $data = json_decode($body, true);
        $code = $data['error']['code'] ?? $data['error'] ?? '';

        return is_string($code) ? (string) preg_replace('/[^A-Za-z0-9_.]/', '', substr($code, 0, 64)) : '';
    }

    public function logLine(): string
    {
        return sprintf('Graph HTTP %d %s', $this->status, $this->errorCode !== '' ? $this->errorCode : '-');
    }
}
