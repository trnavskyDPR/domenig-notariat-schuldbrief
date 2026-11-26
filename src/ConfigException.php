<?php

declare(strict_types=1);

namespace Schuldbrief;

use RuntimeException;

/**
 * Fehlerhafte oder fehlende Einstellung. Die Meldung nennt nur den Namen der
 * Einstellung (nie einen Wert) und darf deshalb ins Log.
 */
final class ConfigException extends RuntimeException
{
}
