<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use Schuldbrief\Http\HttpClient;
use Schuldbrief\Http\HttpResult;

/** Gibt vorbereitete Antworten der Reihe nach zurück und merkt sich die Anfragen. */
final class FakeHttp implements HttpClient
{
    /** @var list<array{url: string, headers: list<string>, body: string}> */
    public array $requests = [];

    /** @param list<HttpResult> $responses */
    public function __construct(private array $responses)
    {
    }

    public function post(string $url, array $headers, string $body): HttpResult
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->responses) ?? new HttpResult(500, '');
    }
}
