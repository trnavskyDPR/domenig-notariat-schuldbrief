<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

use Throwable;

/** Übermittelt einen geprüften Antrag: interne Mails ans Notariat und Bestätigung an die Bank. */
interface OrderService
{
    /**
     * @throws Throwable wenn die interne Mail nicht (vollständig) gesendet werden konnte –
     *                   dann ist der Antrag nicht sicher angekommen und die Bank erhält keine Bestätigung
     * @return bool true, wenn auch die Eingangsbestätigung an die Bank versendet wurde
     */
    public function send(Order $order): bool;
}
