<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/**
 * Geprüfter Antrag auf Schuldbriefbestellung. Lebt nur während der Anfrage im
 * Arbeitsspeicher – wird nie gespeichert und nie geloggt.
 */
final class Order
{
    public function __construct(
        // Angaben zum Grundstück
        public readonly string $grundbuchblatt,
        public readonly string $adresse,
        public readonly string $rang,
        /** Höhe des Schuldbriefes in ganzen Franken */
        public readonly int $betrag,
        // Bank
        public readonly string $glaeubigerin,
        public readonly string $bank,
        // Ansprechperson der Bank (füllt das Formular aus, erhält die Bestätigung)
        public readonly string $vorname,
        public readonly string $nachname,
        public readonly string $email,
        /** international formatiert, z. B. «+41 79 123 45 67» */
        public readonly string $telefon,
        /** Sprache des Formulars (de, fr, en) – für die Bestätigung */
        public readonly string $lang,
        /**
         * Ausweiskopien der Grundeigentümer, nur im Arbeitsspeicher.
         * Gehen ausschliesslich als Anhang der internen Mail ans Notariat hinaus.
         *
         * @var list<Attachment>
         */
        public readonly array $attachments = [],
    ) {
    }

    /** Gleicher Antrag mit vorbereiteten (z. B. verkleinerten) Anhängen. @param list<Attachment> $attachments */
    public function withAttachments(array $attachments): self
    {
        $args = get_object_vars($this);
        $args['attachments'] = $attachments;

        return new self(...$args);
    }

    /** z. B. «CHF 1'250'000» */
    public function betragText(): string
    {
        return 'CHF ' . Amount::format($this->betrag);
    }
}
