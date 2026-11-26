<?php

declare(strict_types=1);

namespace Schuldbrief\Graph;

use JsonException;
use Schuldbrief\Http\HttpClient;

/**
 * Zugang zu Microsoft Graph mit der Entra-ID-App «Schuldbriefbestellung (Atoll)»
 * (Client Credentials). Welche Postfächer die App nutzen darf, legt Exchange fest
 * (RBAC for Applications: nur Mail.Send auf notariat@ und test-notariat@, eingerichtet
 * durch InfoPro) – nicht dieser Code.
 *
 * Das Zugriffs-Token lebt nur während einer Anfrage im Speicher.
 */
final class GraphClient
{
    private const LOGIN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const API_URL = 'https://graph.microsoft.com/v1.0';

    /** 429 = zu viele Anfragen, 503 = Dienst kurz nicht verfügbar. In beiden Fällen ist nichts passiert. */
    private const RETRY_STATUS = [429, 503];
    private const RETRY_DELAY_SECONDS = 1;

    private ?string $token = null;
    private int $tokenExpires = 0;
    /** @var callable(int): void */
    private $sleep;

    public function __construct(
        private HttpClient $http,
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
        /** Nur für Tests: ersetzt sleep(). */
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * POST an Graph, Antwort als Array.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $extraHeaders
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload, array $extraHeaders = []): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = $this->send($path, $body, $extraHeaders);
        if ($result->status === 401) {
            // Token abgelaufen oder widerrufen: einmal neu anmelden.
            $this->token = null;
            $result = $this->send($path, $body, $extraHeaders);
        }
        if (in_array($result->status, self::RETRY_STATUS, true)) {
            // Microsoft überlastet oder kurz nicht verfügbar: die Anfrage wurde nicht ausgeführt.
            // Einmal kurz warten und wiederholen.
            ($this->sleep)(self::RETRY_DELAY_SECONDS);
            $result = $this->send($path, $body, $extraHeaders);
        }
        if ($result->status < 200 || $result->status >= 300) {
            throw new GraphException('Graph-Anfrage fehlgeschlagen', $result->status, GraphException::codeFrom($result->body));
        }
        if (trim($result->body) === '') {
            return [];
        }
        try {
            $data = json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GraphException('Ungültige Antwort von Graph', $result->status);
        }

        return is_array($data) ? $data : [];
    }

    /** @param list<string> $extraHeaders */
    private function send(string $path, string $body, array $extraHeaders): \Schuldbrief\Http\HttpResult
    {
        return $this->http->post(self::API_URL . $path, array_merge([
            'Authorization: Bearer ' . $this->token(),
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders), $body);
    }

    private function token(): string
    {
        if ($this->token !== null && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        $result = $this->http->post(
            sprintf(self::LOGIN_URL, rawurlencode($this->tenantId)),
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]),
        );
        $data = json_decode($result->body, true);
        if ($result->status !== 200 || !is_array($data) || !is_string($data['access_token'] ?? null)) {
            throw new GraphException('Anmeldung bei Microsoft fehlgeschlagen', $result->status, GraphException::codeFrom($result->body));
        }
        $this->token = $data['access_token'];
        $this->tokenExpires = time() + (int) ($data['expires_in'] ?? 3600);

        return $this->token;
    }
}
