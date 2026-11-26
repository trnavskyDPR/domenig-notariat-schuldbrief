<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\TestCase;
use Schuldbrief\Config;
use Schuldbrief\ConfigException;
use Schuldbrief\Graph\GraphClient;
use Schuldbrief\Graph\GraphException;
use Schuldbrief\Http\HttpResult;
use Schuldbrief\Order\Attachment;
use Schuldbrief\Order\GraphOrderService;
use Schuldbrief\Order\Order;

/** Schritt c: interne Mails und Bestätigung an die Bank – mit simulierten Graph-Antworten. */
final class GraphOrderServiceTest extends TestCase
{
    private static function config(string $mode, array $extra = []): Config
    {
        return Config::fromArray($extra + ['APP_MODE' => $mode, 'APP_SECRET' => str_repeat('s', 40)]);
    }

    private static function order(array $attachments = [], string $lang = 'fr'): Order
    {
        return new Order(
            grundbuchblatt: 'Köniz 4567',
            adresse: 'Musterweg 3, 3098 Köniz',
            rang: '1. Rang',
            betrag: 1250000,
            glaeubigerin: 'Valiant Bank AG',
            bank: 'UBS Switzerland AG',
            vorname: 'Anna',
            nachname: 'Muster',
            email: 'anna@bank.ch',
            telefon: '+41 31 123 45 67',
            lang: $lang,
            attachments: $attachments,
        );
    }

    /** Anmeldung und beliebig viele Mails erfolgreich. */
    private static function okHttp(int $mails): FakeHttp
    {
        $responses = [new HttpResult(200, '{"access_token":"tok","expires_in":3600}')];
        for ($i = 0; $i < $mails; $i++) {
            $responses[] = new HttpResult(202, '');
        }

        return new FakeHttp($responses);
    }

    private static function service(FakeHttp $http, Config $config): GraphOrderService
    {
        return new GraphOrderService(new GraphClient($http, 't', 'c', 's', static fn (int $s) => null), $config, APP_ROOT . '/lang');
    }

    /** @return list<array{url: string, payload: array}> nur die Graph-Aufrufe ohne Anmeldung */
    private static function calls(FakeHttp $http): array
    {
        $out = [];
        foreach ($http->requests as $r) {
            if (str_contains($r['url'], 'graph.microsoft.com')) {
                $out[] = ['url' => $r['url'], 'payload' => json_decode($r['body'], true)];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function to(array $call): array
    {
        return array_map(static fn ($r) => $r['emailAddress']['address'], $call['payload']['message']['toRecipients']);
    }

    private static function body(array $call): string
    {
        return $call['payload']['message']['body']['content'];
    }

    public function testLiveSendsInternalMailWithCopiesToAllThreeAndConfirmationToBank(): void
    {
        $http = self::okHttp(2);
        $id = new Attachment('ausweis-1.jpg', 'image/jpeg', 'JPEGDATEN');
        self::assertTrue(self::service($http, self::config('live'))->send(self::order([$id])));

        [$internal, $bank] = self::calls($http);

        // Interne Mail: von notariat@, an alle drei, mit Ausweiskopie, «Antworten» an die Bank
        self::assertStringEndsWith('/users/notariat%40domenig.law/sendMail', $internal['url']);
        self::assertSame(['info@domenig.law', 'notariat@domenig.law', 'schneider@domenig.law'], self::to($internal));
        self::assertSame('Schuldbriefbestellung – Köniz 4567 – Musterweg 3, 3098 Köniz', $internal['payload']['message']['subject']);
        self::assertSame('ausweis-1.jpg', $internal['payload']['message']['attachments'][0]['name']);
        self::assertSame(base64_encode('JPEGDATEN'), $internal['payload']['message']['attachments'][0]['contentBytes']);
        self::assertSame('anna@bank.ch', $internal['payload']['message']['replyTo'][0]['emailAddress']['address']);
        self::assertFalse($internal['payload']['saveToSentItems'], 'keine Kopie in «Gesendete Elemente»');
        $text = self::body($internal);
        foreach (['Grundbuchblatt-Nummer: Köniz 4567', 'Rang des Grundpfandrechts: 1. Rang', "Höhe des Schuldbriefes: CHF 1'250'000",
            'Gläubigerin (Firma): Valiant Bank AG', 'Name bestehende Bank: UBS Switzerland AG', 'Name: Anna Muster',
            'Telefonnummer: +41 31 123 45 67', 'Sprache: Französisch', 'Ausweiskopien: 1 Datei(en)', 'sind im Anhang'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }

        // Bestätigung: an die Bank, Französisch, ohne Anhang, nur Referenz
        self::assertSame(['anna@bank.ch'], self::to($bank));
        self::assertSame('Accusé de réception – commande de cédule hypothécaire – feuillet Köniz 4567', $bank['payload']['message']['subject']);
        self::assertArrayNotHasKey('attachments', $bank['payload']['message']);
        self::assertSame('notariat@domenig.law', $bank['payload']['message']['replyTo'][0]['emailAddress']['address']);
        $text = self::body($bank);
        self::assertStringContainsString('Bonjour Anna Muster', $text);
        self::assertStringContainsString('Musterweg 3, 3098 Köniz', $text);
        self::assertStringContainsString('1 fichier(s)', $text);
        self::assertStringContainsString("CHF 1'000.- et 1'500.-", $text, 'Gebührenhinweis');
        self::assertStringContainsString('+41 58 531 20 00', $text);
        foreach (['1\'250\'000', '1250000', 'Valiant', 'UBS', '1. Rang'] as $notInBankMail) {
            self::assertStringNotContainsString($notInBankMail, $text, 'kein Betrag, keine Bankangaben in der Bestätigung');
        }
    }

    public function testIdCopiesOnlyToConfiguredRecipients(): void
    {
        $http = self::okHttp(3);
        $config = self::config('live', ['ID_COPY_RECIPIENTS' => 'notariat@domenig.law']);
        self::service($http, $config)->send(self::order([new Attachment('ausweis-1.pdf', 'application/pdf', '%PDF-x')]));

        [$withCopy, $withoutCopy] = self::calls($http);
        self::assertSame(['notariat@domenig.law'], self::to($withCopy));
        self::assertArrayHasKey('attachments', $withCopy['payload']['message']);
        self::assertSame(['info@domenig.law', 'schneider@domenig.law'], self::to($withoutCopy), 'je ein eigenes Exemplar');
        self::assertArrayNotHasKey('attachments', $withoutCopy['payload']['message']);
        self::assertStringContainsString('separat an notariat@domenig.law gesendet', self::body($withoutCopy));
    }

    public function testIdCopyRecipientsOutsideInternalListIsAConfigError(): void
    {
        $config = self::config('live', ['ID_COPY_RECIPIENTS' => 'jemand@domenig.law']);
        $this->expectException(ConfigException::class);
        self::service(self::okHttp(3), $config)->send(self::order([new Attachment('ausweis-1.pdf', 'application/pdf', '%PDF-x')]));
    }

    public function testLargeAttachmentsAreSplitOverSeveralMails(): void
    {
        $http = self::okHttp(3);
        $files = [
            new Attachment('ausweis-1.pdf', 'application/pdf', str_repeat('a', 1_200_000)),
            new Attachment('ausweis-2.pdf', 'application/pdf', str_repeat('b', 1_200_000)),
            new Attachment('ausweis-3.pdf', 'application/pdf', str_repeat('c', 1_200_000)),
        ];
        self::service($http, self::config('live'))->send(self::order($files, 'de'));

        [$part1, $part2, $bank] = self::calls($http);
        self::assertStringEndsWith('(Teil 1 von 2)', $part1['payload']['message']['subject']);
        self::assertStringEndsWith('(Teil 2 von 2)', $part2['payload']['message']['subject']);
        self::assertCount(2, $part1['payload']['message']['attachments']);
        self::assertCount(1, $part2['payload']['message']['attachments']);
        self::assertStringContainsString('auf 2 E-Mails mit demselben Betreff verteilt', self::body($part1));
        self::assertSame(['anna@bank.ch'], self::to($bank));
        self::assertStringContainsString('Ausweiskopien: 3 Datei(en)', self::body($bank));
    }

    public function testTestModeSendsEverythingToTheTestMailbox(): void
    {
        $http = self::okHttp(2);
        self::service($http, self::config('test'))->send(self::order([new Attachment('ausweis-1.png', 'image/png', 'PNG')], 'en'));

        foreach (self::calls($http) as $call) {
            self::assertStringEndsWith('/users/test-notariat%40domenig.law/sendMail', $call['url'], 'Absender = Testpostfach');
            self::assertSame(['test-notariat@domenig.law'], self::to($call));
            self::assertStringStartsWith('[TEST] ', $call['payload']['message']['subject']);
        }
        self::assertStringContainsString('Dear Anna Muster', self::body(self::calls($http)[1]), 'Bestätigung in der Sprache der Bank');
    }

    public function testFailedInternalMailIsReportedAndNoConfirmationIsSent(): void
    {
        $http = new FakeHttp([
            new HttpResult(200, '{"access_token":"tok","expires_in":3600}'),
            new HttpResult(403, '{"error":{"code":"ErrorAccessDenied","message":"Anna Muster"}}'),
        ]);
        try {
            self::service($http, self::config('live'))->send(self::order());
            self::fail('Fehler nicht gemeldet');
        } catch (GraphException $e) {
            self::assertSame('Graph HTTP 403 ErrorAccessDenied', $e->logLine());
            self::assertStringNotContainsString('Anna', $e->logLine(), 'Microsofts Meldungstext kommt nie ins Log');
        }
        self::assertCount(1, self::calls($http), 'keine Bestätigung an die Bank');
    }

    public function testFailedConfirmationKeepsTheOrder(): void
    {
        $http = new FakeHttp([
            new HttpResult(200, '{"access_token":"tok","expires_in":3600}'),
            new HttpResult(202, ''),
            new HttpResult(400, '{"error":{"code":"ErrorInvalidRecipients"}}'),
        ]);
        $log = ini_set('error_log', '/dev/null');
        $result = self::service($http, self::config('live'))->send(self::order());
        ini_set('error_log', (string) $log);
        self::assertFalse($result, 'Antrag ist angekommen, nur die Bestätigung nicht');
        self::assertCount(2, self::calls($http));
    }

    public function testWithoutAttachmentsOneMailToAll(): void
    {
        $http = self::okHttp(2);
        self::service($http, self::config('live'))->send(self::order([], 'de'));
        [$internal] = self::calls($http);
        self::assertSame(['info@domenig.law', 'notariat@domenig.law', 'schneider@domenig.law'], self::to($internal));
        self::assertStringContainsString('Es wurden keine Ausweiskopien mitgeschickt.', self::body($internal));
    }
}
