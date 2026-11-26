<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\TestCase;
use Schuldbrief\Config;
use Schuldbrief\ConfigException;

final class ConfigTest extends TestCase
{
    public function testWithoutSettingsRunsInMockMode(): void
    {
        $config = Config::fromArray([]);
        self::assertSame('mock', $config->mode());
        self::assertTrue($config->isMock());
        self::assertGreaterThanOrEqual(32, strlen($config->secret()), 'Mock-Modus läuft ohne .env');
    }

    public function testInvalidModeIsRejected(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArray(['APP_MODE' => 'produktiv'])->mode();
    }

    public function testSecretIsRequiredOutsideMockMode(): void
    {
        foreach (['test', 'live'] as $mode) {
            foreach (['', 'zu-kurz'] as $secret) {
                try {
                    Config::fromArray(['APP_MODE' => $mode, 'APP_SECRET' => $secret])->secret();
                    self::fail($mode . ': fehlender Schlüssel nicht erkannt');
                } catch (ConfigException $e) {
                    self::assertStringNotContainsString('zu-kurz', $e->getMessage(), 'nie den Wert in der Meldung');
                }
            }
        }
        $secret = str_repeat('a', 32);
        self::assertSame($secret, Config::fromArray(['APP_MODE' => 'live', 'APP_SECRET' => $secret])->secret());
    }

    public function testPrivacyUrlFallsBackToGerman(): void
    {
        $config = Config::fromArray(['PRIVACY_URL_FR' => 'https://www.domenig.law/fr/protection-des-donnees']);
        self::assertSame('https://www.domenig.law/fr/protection-des-donnees', $config->privacyUrl('fr'));
        self::assertSame('https://www.domenig.law/datenschutz', $config->privacyUrl('en'));
    }
}
