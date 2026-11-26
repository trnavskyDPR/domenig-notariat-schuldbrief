<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

final class Response
{
    /** Keine Fremdquellen: alles kommt vom eigenen Server. */
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; "
            . "font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cache-Control' => 'no-store',
    ];

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
    }

    /** Relative Weiterleitung (z. B. «?view=confirmed&…»), 303 nach POST. */
    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [$name => $value] + $this->headers);
    }

    public static function redirect(string $location): self
    {
        return new self(303, '', ['Location' => $location]);
    }

    public function header(string $name): ?string
    {
        return $this->allHeaders()[$name] ?? null;
    }

    /** @return array<string, string> */
    public function allHeaders(): array
    {
        return $this->headers + self::SECURITY_HEADERS;
    }

    public function send(bool $withBody = true): void
    {
        header_remove('X-Powered-By');
        http_response_code($this->status);
        foreach ($this->allHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($withBody) {
            echo $this->body;
        }
    }
}
