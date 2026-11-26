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

    public function testRecipientsDefaultToAllThree(): void
    {
        $config = Config::fromArray(['APP_MODE' => 'live']);
        $three = ['info@domenig.law', 'notariat@domenig.law', 'schneider@domenig.law'];
        self::assertSame($three, $config->internalRecipients());
        self::assertSame($three, $config->idCopyRecipients(), 'Ausweiskopien an alle drei (Entscheid 26.11.2026)');
        self::assertSame('notariat@domenig.law', $config->senderMailbox());
        self::assertSame('anna@bank.ch', $config->bankRecipient('anna@bank.ch'));
    }

    public function testTestModeUsesOnlyTheTestMailbox(): void
    {
        $config = Config::fromArray(['APP_MODE' => 'test']);
        self::assertSame(['test-notariat@domenig.law'], $config->internalRecipients());
        self::assertSame(['test-notariat@domenig.law'], $config->idCopyRecipients());
        self::assertSame('test-notariat@domenig.law', $config->senderMailbox());
        self::assertSame('test-notariat@domenig.law', $config->bankRecipient('anna@bank.ch'), 'nie an echte Banken');
    }

    public function testRecipientListsAreCleanedAndChecked(): void
    {
        $config = Config::fromArray(['APP_MODE' => 'live', 'INTERNAL_RECIPIENTS' => ' Notariat@domenig.law, notariat@domenig.law ,info@domenig.law,']);
        self::assertSame(['notariat@domenig.law', 'info@domenig.law'], $config->internalRecipients());

        $this->expectException(ConfigException::class);
        Config::fromArray(['APP_MODE' => 'live', 'INTERNAL_RECIPIENTS' => 'kein-mail'])->internalRecipients();
    }

    public function testGraphCredentialsAreRequired(): void
    {
        $ok = Config::fromArray([
            'GRAPH_TENANT_ID' => 'ae4b51b9-1564-4bd2-a852-6100861922ec',
            'GRAPH_CLIENT_ID' => '00000000-0000-0000-0000-000000000001',
            'GRAPH_CLIENT_SECRET' => 'geheim',
        ]);
        self::assertSame('geheim', $ok->graphCredentials()[2]);

        try {
            Config::fromArray(['GRAPH_TENANT_ID' => 'x', 'GRAPH_CLIENT_SECRET' => 'geheim'])->graphCredentials();
            self::fail('fehlende Angaben nicht erkannt');
        } catch (ConfigException $e) {
            self::assertStringNotContainsString('geheim', $e->getMessage(), 'nie den Schlüssel in der Meldung');
        }
    }

    public function testPrivacyUrlFallsBackToGerman(): void
    {
        $config = Config::fromArray(['PRIVACY_URL_FR' => 'https://www.domenig.law/fr/protection-des-donnees']);
        self::assertSame('https://www.domenig.law/fr/protection-des-donnees', $config->privacyUrl('fr'));
        self::assertSame('https://www.domenig.law/datenschutz', $config->privacyUrl('en'));
    }
}
