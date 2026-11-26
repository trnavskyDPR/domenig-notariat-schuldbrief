<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\TestCase;
use Schuldbrief\I18n;

final class I18nTest extends TestCase
{
    private const DIR = APP_ROOT . '/lang';

    public function testAllLanguagesHaveTheSameKeys(): void
    {
        $de = require self::DIR . '/de.php';
        foreach (['fr', 'en'] as $lang) {
            $other = require self::DIR . '/' . $lang . '.php';
            self::assertSame([], array_values(array_diff(array_keys($de), array_keys($other))), $lang . ': fehlende Schlüssel');
            self::assertSame([], array_values(array_diff(array_keys($other), array_keys($de))), $lang . ': überzählige Schlüssel');
            foreach ($de as $key => $value) {
                self::assertSame(gettype($value), gettype($other[$key]), $lang . ': ' . $key);
            }
        }
    }

    public function testPlaceholdersMatchGerman(): void
    {
        $de = require self::DIR . '/de.php';
        foreach (['fr', 'en'] as $lang) {
            $other = require self::DIR . '/' . $lang . '.php';
            foreach ($de as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }
                preg_match_all('/\{(\w+)\}/', $value, $expected);
                preg_match_all('/\{(\w+)\}/', $other[$key], $actual);
                sort($expected[1]);
                sort($actual[1]);
                self::assertSame($expected[1], $actual[1], $lang . ': ' . $key);
            }
        }
    }

    public function testEveryValidatorErrorHasAText(): void
    {
        $de = require self::DIR . '/de.php';
        foreach (['grundbuchblatt', 'adresse', 'rang', 'betrag', 'glaeubigerin', 'bank', 'vorname', 'nachname', 'email', 'telefon'] as $field) {
            self::assertArrayHasKey('error.' . $field . '.required', $de, $field);
            self::assertArrayHasKey('error.' . $field . '.invalid', $de, $field);
        }
    }

    /** Schweizer Hochdeutsch: nie Eszett – in Texten, Templates, Code und Doku. */
    public function testNoEszettAnywhere(): void
    {
        $files = array_merge(
            glob(APP_ROOT . '/lang/*.php'),
            glob(APP_ROOT . '/templates/*.php'),
            glob(APP_ROOT . '/src/*.php'),
            glob(APP_ROOT . '/src/*/*.php'),
            glob(APP_ROOT . '/public/*.php'),
            glob(APP_ROOT . '/*.md'),
        );
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            self::assertStringNotContainsString("\u{00DF}", (string) file_get_contents($file), basename($file));
        }
    }

    /** «\\u{a0}» wirkt nur in doppelten Anführungszeichen – in einfachen stünde es wörtlich im Text. */
    public function testNoLiteralUnicodeEscapes(): void
    {
        foreach (I18n::LANGS as $lang) {
            foreach (require self::DIR . '/' . $lang . '.php' as $key => $value) {
                self::assertStringNotContainsString('\\u{', (string) (is_string($value) ? $value : ''), $lang . ': ' . $key);
            }
        }
    }

    public function testPick(): void
    {
        self::assertSame('fr', I18n::pick('FR'));
        self::assertSame('en', I18n::pick(' en '));
        self::assertSame('de', I18n::pick('it'));
        self::assertSame('de', I18n::pick(['fr']));
        self::assertSame('de', I18n::pick(null));
    }

    public function testFeeTextIsVerbatim(): void
    {
        self::assertSame(
            'Unsere Gebühren betragen zwischen CHF 1\'000.- und 1\'500.-. Dazu kommen noch die Gebühren vom Grundbuchamt im Umfang von ca. CHF 150.00 (abhängig von den konkreten Eintragungen).',
            (new I18n('de', self::DIR))->t('fees.text'),
        );
    }
}
