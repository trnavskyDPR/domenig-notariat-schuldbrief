<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

/** Minimale HTTP-Schnittstelle, damit Graph-Aufrufe in Tests ohne Netz geprüft werden können. */
interface HttpClient
{
    /** @param list<string> $headers z. B. ['Content-Type: application/json'] */
    public function post(string $url, array $headers, string $body): HttpResult;
}
