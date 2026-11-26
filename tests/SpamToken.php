<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use DateTimeImmutable;
use Schuldbrief\Config;
use Schuldbrief\Security\Signer;

/** Gültiger Zeitstempel fürs Formular, wie ihn ein Mensch nach einer Minute absendet. */
final class SpamToken
{
    public static function valid(DateTimeImmutable $now, ?string $secret = null): string
    {
        $secret ??= Config::fromArray(['APP_MODE' => 'mock'])->secret();

        return (new Signer($secret))->sign(['t' => $now->getTimestamp() - 60], 86400, $now);
    }
}
