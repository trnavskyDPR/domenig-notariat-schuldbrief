<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

use Schuldbrief\ConfigException;

/** HTTPS-Aufrufe über die PHP-Erweiterung curl. Zertifikate werden immer geprüft. */
final class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeoutSeconds = 10)
    {
        if (!function_exists('curl_init')) {
            throw new ConfigException('Die PHP-Erweiterung curl fehlt.');
        }
    }

    public function post(string $url, array $headers, string $body): HttpResult
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $errno !== 0) {
            // Nur die Fehlernummer – nie URL-Parameter oder Inhalte.
            throw new NetworkException('Netzwerkfehler (curl ' . $errno . ')');
        }

        return new HttpResult($status, (string) $response);
    }
}
