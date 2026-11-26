<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

final class HttpResult
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
