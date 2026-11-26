<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schuldbrief\Order\Amount;

final class AmountTest extends TestCase
{
    /** @return array<string, array{0: string, 1: int}> */
    public static function validInputs(): array
    {
        return [
            'nur Ziffern' => ['500000', 500000],
            'Apostroph' => ["500'000", 500000],
            'typografischer Apostroph' => ['1’250’000', 1250000],
            'Leerzeichen' => ['1 250 000', 1250000],
            'geschütztes Leerzeichen' => ["1\u{00A0}250\u{00A0}000", 1250000],
            'schmales Leerzeichen' => ["1\u{202F}250\u{202F}000", 1250000],
            'Komma als Tausender' => ['1,250,000', 1250000],
            'Punkt als Tausender' => ['1.250.000', 1250000],
            'Strich am Schluss' => ["500'000.-", 500000],
            'Gedankenstrich am Schluss' => ["500'000.–", 500000],
            'Rappen null' => ["500'000.00", 500000],
            'Komma Rappen null' => ['500000,00', 500000],
            'CHF vorne' => ["CHF 500'000", 500000],
            'chf hinten' => ['500000 chf', 500000],
            'Fr. vorne' => ["Fr. 500'000.–", 500000],
        ];
    }

    #[DataProvider('validInputs')]
    public function testParsesSwissFormats(string $input, int $expected): void
    {
        self::assertSame($expected, Amount::parse($input));
    }

    /** @return array<string, array{0: string}> */
    public static function invalidInputs(): array
    {
        return [
            'leer' => [''],
            'Rappen' => ["500'000.50"],
            'Dezimalpunkt' => ['1000.5'],
            'Buchstaben' => ['fünfhunderttausend'],
            'negativ' => ['-500000'],
            'Formel' => ['500000+1'],
            'falsche Gruppierung' => ['1.25.000'],
            'zu viele Ziffern' => ['1234567890123'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(string $input): void
    {
        self::assertSame(Amount::INVALID, Amount::parse($input));
    }

    public function testFormatsWithSwissApostrophe(): void
    {
        self::assertSame("1'000", Amount::format(1000));
        self::assertSame("1'250'000", Amount::format(1250000));
        self::assertSame("999'999'999", Amount::format(Amount::MAX));
    }
}
