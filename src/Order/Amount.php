<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/**
 * Höhe des Schuldbriefes in ganzen Franken.
 *
 * Eingabe ist bewusst grosszügig, damit Banken so tippen können, wie sie es gewohnt sind:
 *   1000 · 1'000 · 1’000 · 1 000 · 1,000 · 1.000 · 1'000.– · 1'000.- · 1'000.00 · CHF 1'000 · Fr. 1'000
 * Rappen (z. B. 1'000.50) werden abgelehnt: Schuldbriefe lauten auf ganze Franken.
 * Ausgabe immer im Schweizer Format: 1'250'000.
 */
final class Amount
{
    public const MIN = 1000;
    /** Obergrenze gegen Tippfehler und Überlauf (knapp eine Milliarde). */
    public const MAX = 999999999;

    /** Ergebnis von parse(), wenn die Eingabe keine gültige Zahl ist. */
    public const INVALID = -1;

    /**
     * Wandelt die Eingabe in Franken um.
     * Gibt INVALID zurück, wenn es keine ganze Frankenzahl ist. Min/Max prüft der Validator.
     */
    public static function parse(string $input): int
    {
        $value = trim($input);
        // Währung vorne oder hinten weglassen: «CHF», «Fr.», «SFr.»
        $value = (string) preg_replace('/^(chf|s?fr\.?)\s*/iu', '', $value);
        $value = (string) preg_replace('/\s*(chf|s?fr\.?)$/iu', '', $value);
        // Ganze Franken am Schluss: «.–», «.-», «.—», «,-», «.00», «,00»
        $value = (string) preg_replace('/[.,](-|–|—|00)$/u', '', $value);
        // Tausendertrennzeichen: Apostroph (gerade und typografisch) und Leerzeichen aller Art
        $value = (string) preg_replace("/['’‘\\s\\x{00A0}\\x{202F}\\x{2009}]/u", '', $value);

        // Punkt oder Komma nur als Tausendertrennzeichen (immer genau drei Ziffern danach)
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value) || preg_match('/^\d{1,3}(,\d{3})+$/', $value)) {
            $value = str_replace(['.', ','], '', $value);
        }
        if (!preg_match('/^\d{1,12}$/', $value)) {
            return self::INVALID;
        }

        return (int) $value;
    }

    /** 1250000 => «1'250'000» */
    public static function format(int $francs): string
    {
        return number_format($francs, 0, '.', "'");
    }
}
