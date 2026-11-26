<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/**
 * Geprüfte Datei (Ausweiskopie eines Grundeigentümers). Lebt nur während der Anfrage im
 * Arbeitsspeicher – wird nie gespeichert und nie geloggt. Der Dateiname ist
 * bewusst neutral («ausweis-1.jpg»): der Originalname der Datei wird nicht
 * übernommen, weil er oft den Namen der Person enthält.
 */
final class Attachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mime,
        public readonly string $content,
    ) {
    }

    public function size(): int
    {
        return strlen($this->content);
    }
}
