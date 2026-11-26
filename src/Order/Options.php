<?php

declare(strict_types=1);

namespace Schuldbrief\Order;

/** Auswahllisten im Formular (gleich wie im Beglaubigungstool). */
final class Options
{
    public const DIAL_OTHER = 'other';

    /** Ländervorwahl => Anzeige. Bei «Andere» gibt man die Nummer mit + ein. */
    public const DIAL_CODES = [
        '+41' => 'CH +41',
        '+423' => 'LI +423',
        '+49' => 'DE +49',
        '+43' => 'AT +43',
        '+33' => 'FR +33',
        '+39' => 'IT +39',
        '+34' => 'ES +34',
        '+351' => 'PT +351',
        '+31' => 'NL +31',
        '+32' => 'BE +32',
        '+352' => 'LU +352',
        '+44' => 'GB +44',
        '+1' => 'US/CA +1',
    ];

    public const DIAL_DEFAULT = '+41';

    /** Länder, deren nationale Nummern die führende 0 behalten. */
    public const KEEP_LEADING_ZERO = ['+39'];
}
