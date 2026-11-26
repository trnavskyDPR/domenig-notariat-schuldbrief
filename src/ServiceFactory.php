<?php

declare(strict_types=1);

namespace Schuldbrief;

use Schuldbrief\Graph\GraphClient;
use Schuldbrief\Http\CurlHttpClient;
use Schuldbrief\Order\GraphOrderService;
use Schuldbrief\Order\OrderService;

/** Wählt je nach APP_MODE, wie ein Antrag übermittelt wird. */
final class ServiceFactory
{
    /**
     * mock: null – nichts wird gesendet, Ausweiskopien werden geprüft und verworfen.
     * test: Mails über Graph, aber alle ans Testpostfach (TEST_MAILBOX), Betreff mit «[TEST]».
     * live: Mails über Graph an die echten Empfänger.
     */
    public static function orders(Config $config, string $root): ?OrderService
    {
        if ($config->isMock()) {
            return null;
        }
        [$tenant, $client, $secret] = $config->graphCredentials();
        $graph = new GraphClient(new CurlHttpClient($config->graphTimeout()), $tenant, $client, $secret);

        return new GraphOrderService($graph, $config, $root . '/lang');
    }
}
