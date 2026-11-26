<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schuldbrief\Order\OrderValidator;

final class OrderValidatorTest extends TestCase
{
    /** Ein gültiger Antrag, wie ihn eine Bank ausfüllt. @return array<string, string> */
    public static function valid(array $override = []): array
    {
        return $override + [
            'grundbuchblatt' => 'Bern 4/1234',
            'adresse' => 'Musterweg 3, 3011 Bern',
            'rang' => "2. Rang, im Nachgang zu CHF 300'000.–",
            'betrag' => "1'250'000",
            'glaeubigerin' => 'Berner Kantonalbank AG (BEKB | BCBE)',
            'bank' => 'UBS Switzerland AG',
            'vorname' => 'Anna-Lena',
            'nachname' => "D'Amico Müller",
            'email' => 'anna.muster@bank.ch',
            'vorwahl' => '+41',
            'telefon' => '031 123 45 67',
            'datenschutz' => '1',
        ];
    }

    public function testValidOrder(): void
    {
        $result = (new OrderValidator())->validate(self::valid(), 'fr');
        self::assertSame([], $result->errors);
        self::assertTrue($result->isValid());
        $order = $result->order;
        self::assertSame('Bern 4/1234', $order->grundbuchblatt);
        self::assertSame(1250000, $order->betrag);
        self::assertSame("CHF 1'250'000", $order->betragText());
        self::assertSame('+41 31 123 45 67', $order->telefon);
        self::assertSame('fr', $order->lang);
    }

    public function testEmptyFormListsAllRequiredFieldsInFormOrder(): void
    {
        $result = (new OrderValidator())->validate([], 'de');
        self::assertFalse($result->isValid());
        self::assertSame(
            ['grundbuchblatt', 'adresse', 'rang', 'betrag', 'glaeubigerin', 'bank',
                'vorname', 'nachname', 'email', 'telefon', 'datenschutz'],
            array_keys($result->errors),
        );
        foreach ($result->errors as $field => [$key]) {
            self::assertStringEndsWith('.required', $key, $field);
        }
    }

    public function testInputIsCleanedAndKeptForRedisplay(): void
    {
        $result = (new OrderValidator())->validate(self::valid([
            'adresse' => "  Musterweg   3,\n3011 Bern ",
            'email' => 'kaputt',
        ]), 'de');
        self::assertSame('Musterweg 3, 3011 Bern', $result->values['adresse']);
        self::assertSame('kaputt', $result->values['email']);
        self::assertSame(['email'], array_keys($result->errors));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function invalidFields(): array
    {
        return [
            'Grundbuchblatt ohne Ziffer' => ['grundbuchblatt', 'Bern', 'error.grundbuchblatt.invalid'],
            'Grundbuchblatt mit HTML' => ['grundbuchblatt', '<b>12</b>', 'error.grundbuchblatt.invalid'],
            'Adresse nur Ziffern' => ['adresse', '3011', 'error.adresse.invalid'],
            'Rang mit Spitzklammern' => ['rang', '<script>', 'error.rang.invalid'],
            'Rang nur Satzzeichen' => ['rang', '...', 'error.rang.invalid'],
            'Betrag mit Rappen' => ['betrag', "500'000.50", 'error.betrag.invalid'],
            'Betrag zu klein' => ['betrag', '999', 'error.betrag.too_small'],
            'Betrag zu gross' => ['betrag', '1000000000', 'error.betrag.too_big'],
            'Gläubigerin mit Dollarzeichen' => ['glaeubigerin', '$$$', 'error.glaeubigerin.invalid'],
            'Bank zu lang' => ['bank', str_repeat('a', 101), 'error.too_long'],
            'Vorname mit Ziffer' => ['vorname', 'Anna1', 'error.vorname.invalid'],
            'E-Mail ohne @' => ['email', 'anna.bank.ch', 'error.email.invalid'],
            'Telefon zu kurz' => ['telefon', '123', 'error.telefon.invalid'],
            'Datenschutz nicht bestätigt' => ['datenschutz', '', 'error.datenschutz.required'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function testInvalidField(string $field, string $value, string $expectedKey): void
    {
        $result = (new OrderValidator())->validate(self::valid([$field => $value]), 'de');
        self::assertFalse($result->isValid());
        self::assertSame([$field], array_keys($result->errors));
        self::assertSame($expectedKey, $result->errors[$field][0]);
    }

    public function testAmountErrorNamesTheLimitInSwissFormat(): void
    {
        $result = (new OrderValidator())->validate(self::valid(['betrag' => '500']), 'de');
        self::assertSame(['min' => "1'000"], $result->errors['betrag'][1]);
    }

    public function testManipulatedDialCodeIsRejected(): void
    {
        $result = (new OrderValidator())->validate(self::valid(['vorwahl' => '+999']), 'de');
        self::assertSame(['vorwahl'], array_keys($result->errors));
    }

    public function testOtherDialCodeNeedsInternationalNumber(): void
    {
        $validator = new OrderValidator();
        $result = $validator->validate(self::valid(['vorwahl' => 'other', 'telefon' => '612 34 56 78']), 'de');
        self::assertSame('error.telefon.needs_code', $result->errors['telefon'][0]);

        $result = $validator->validate(self::valid(['vorwahl' => 'other', 'telefon' => '+34 612 34 56 78']), 'de');
        self::assertTrue($result->isValid());
        self::assertSame('+34 612345678', $result->order->telefon);
    }

    public function testArraysInsteadOfStringsAreTreatedAsEmpty(): void
    {
        $result = (new OrderValidator())->validate(self::valid(['grundbuchblatt' => ['1234']]), 'de');
        self::assertSame('error.grundbuchblatt.required', $result->errors['grundbuchblatt'][0]);
    }

    public function testInvalidUtf8IsDiscarded(): void
    {
        $result = (new OrderValidator())->validate(self::valid(['bank' => "UBS\xC3\x28"]), 'de');
        self::assertSame('', $result->values['bank']);
        self::assertSame('error.bank.required', $result->errors['bank'][0]);
    }
}
