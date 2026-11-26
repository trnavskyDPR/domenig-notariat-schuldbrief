<?php

declare(strict_types=1);

namespace Schuldbrief\Http;

use RuntimeException;

/**
 * Microsoft (oder ein anderer Dienst) war nicht erreichbar. Die Meldung enthält nur die
 * curl-Fehlernummer (z. B. 6 = Name nicht auflösbar, 28 = Zeitüberschreitung) und darf ins Log.
 */
final class NetworkException extends RuntimeException
{
}
