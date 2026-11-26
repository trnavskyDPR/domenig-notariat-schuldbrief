<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Schuldbrief\App;
use Schuldbrief\Config;
use Schuldbrief\Graph\GraphException;
use Schuldbrief\Http\Response;
use Schuldbrief\Order\Order;
use Schuldbrief\Order\OrderService;

/** Durchstich durch Controller und Templates im Mock-Modus. */
final class AppTest extends TestCase
{
    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26 10:00', new DateTimeZone('Europe/Zurich'));
    }

    private static function app(array $config = ['APP_MODE' => 'mock']): App
    {
        // Tests: temporäre Testdateien gelten als echter Upload.
        return new App(Config::fromArray($config), APP_ROOT, self::now(), static fn (string $tmp): bool => true);
    }

    /** Absenden wie der Browser: Felder plus eine Ausweiskopie (1×1-PNG), sofern nicht anders angegeben. */
    private static function submit(array $post, ?App $app = null, ?array $files = null): Response
    {
        return ($app ?? self::app())->handle('POST', [], $post, ['ausweis' => $files ?? UploadFixtures::files([UploadFixtures::png()])]);
    }

    private static function validPost(array $override = []): array
    {
        // «ft»: signierter Zeitstempel, wie ihn ein Mensch nach einer Minute absendet (Spamschutz).
        return $override + ['lang' => 'de', 'ft' => SpamToken::valid(self::now())] + OrderValidatorTest::valid();
    }

    public function testFormPageInAllLanguages(): void
    {
        $titles = ['de' => 'Antrag Schuldbriefbestellung', 'fr' => 'Commande de cédule hypothécaire', 'en' => 'Mortgage note order'];
        foreach ($titles as $lang => $title) {
            $response = self::app()->handle('GET', ['lang' => $lang], []);
            self::assertSame(200, $response->status);
            self::assertStringContainsString('<h1>' . $title . '</h1>', $response->body, $lang);
            self::assertStringContainsString('<html lang="' . ($lang === 'de' ? 'de-CH' : ($lang === 'fr' ? 'fr-CH' : 'en')) . '">', $response->body);
        }
    }

    public function testEveryFieldHasALabelAndNoCalendar(): void
    {
        $body = self::app()->handle('GET', [], [])->body;
        foreach (['grundbuchblatt', 'adresse', 'rang', 'betrag', 'glaeubigerin', 'bank', 'vorname', 'nachname', 'email', 'vorwahl', 'telefon', 'datenschutz'] as $field) {
            self::assertMatchesRegularExpression('/<label[^>]* for="f-' . $field . '"/', $body, $field);
            self::assertStringContainsString('id="f-' . $field . '" name="' . $field . '"', $body, $field);
        }
        self::assertStringContainsString('<label for="f-ausweis">', $body);
        self::assertStringContainsString('novalidate', $body, 'Prüfung nur auf dem Server');
        self::assertStringNotContainsString('name="slot"', $body);
    }

    public function testFeeNoticeIsShownBeforeSubmit(): void
    {
        $body = self::app()->handle('GET', [], [])->body;
        $fee = strpos($body, 'Hinweise zu den Gebühren');
        self::assertNotFalse($fee);
        self::assertStringContainsString("CHF 1&apos;000.- und 1&apos;500.-", $body);
        self::assertLessThan(strpos($body, 'type="submit"'), $fee, 'Hinweis steht vor dem Absende-Knopf');
    }

    public function testPrivacyCheckboxLinksToPrivacyPolicyPerLanguage(): void
    {
        $app = self::app(['APP_MODE' => 'mock', 'PRIVACY_URL_FR' => 'https://www.domenig.law/fr/protection-des-donnees']);
        self::assertStringContainsString('href="https://www.domenig.law/fr/protection-des-donnees" target="_blank"', $app->handle('GET', ['lang' => 'fr'], [])->body);
        self::assertStringContainsString('href="https://www.domenig.law/datenschutz" target="_blank"', $app->handle('GET', ['lang' => 'de'], [])->body);
    }

    public function testSecurityHeaders(): void
    {
        $response = self::app()->handle('GET', [], []);
        self::assertStringContainsString("default-src 'none'", (string) $response->header('Content-Security-Policy'));
        self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'));
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
    }

    public function testNoThirdPartyResourcesAndNoInlineCode(): void
    {
        $body = self::app()->handle('GET', [], [])->body;
        preg_match_all('/(?:src|href)="(https?:)?\/\/([^"\/]+)/', $body, $m);
        self::assertSame(['www.domenig.law'], array_values(array_unique($m[2])), 'Nur Links auf domenig.law, keine eingebundenen Fremdquellen');
        self::assertDoesNotMatchRegularExpression('/<(script|link|img)[^>]+(src|href)="https?:/', $body);
        self::assertDoesNotMatchRegularExpression('/<script>|<style|style="|onclick=/', $body, 'CSP: keine Inline-Skripte oder -Stile');
    }

    public function testInvalidSubmitShowsErrorsAndKeepsInputEscaped(): void
    {
        $response = self::submit(self::validPost(['email' => 'kaputt', 'adresse' => '<b>Musterweg 3</b>']));
        self::assertSame(200, $response->status);
        self::assertStringContainsString('id="error-summary"', $response->body);
        self::assertStringContainsString('href="#f-email"', $response->body, 'Fehlerübersicht verlinkt das Feld');
        self::assertStringContainsString('aria-describedby="hint-email err-email" aria-invalid="true"', $response->body);
        self::assertStringContainsString('value="kaputt"', $response->body);
        self::assertStringContainsString('value="&lt;b&gt;Musterweg 3&lt;/b&gt;"', $response->body, 'Eingaben werden escaped');
        self::assertStringNotContainsString('<b>Musterweg', $response->body);
        self::assertStringContainsString('value="Bern 4/1234"', $response->body, 'gültige Felder bleiben ausgefüllt');
    }

    public function testErrorsAreShownInTheChosenLanguage(): void
    {
        $response = self::submit(self::validPost(['lang' => 'fr', 'betrag' => '500']));
        self::assertStringContainsString('Le montant doit être d’au moins CHF 1&apos;000.', $response->body);
    }

    public function testValidSubmitRedirectsToConfirmationWithoutOrderData(): void
    {
        $response = self::submit(self::validPost(['lang' => 'en']));
        self::assertSame(303, $response->status);
        $location = (string) $response->header('Location');
        self::assertStringStartsWith('?view=confirmed&lang=en&t=', $location);
        $payload = base64_decode(strtr(explode('.', substr($location, strpos($location, 't=') + 2))[0], '-_', '+/'));
        foreach (['1234', 'Musterweg', 'Anna', 'bank.ch', '1250000'] as $data) {
            self::assertStringNotContainsString($data, $payload . $location, 'keine Antragsdaten in der URL: ' . $data);
        }

        parse_str(substr($location, 1), $get);
        $page = self::app()->handle('GET', $get, []);
        self::assertSame(200, $page->status);
        self::assertStringContainsString('Thank you – we have received your order', $page->body);
        self::assertStringContainsString('Test mode: nothing was transmitted', $page->body);
        self::assertStringContainsString('Notes on fees', $page->body);
        self::assertStringContainsString('noindex', $page->body);
    }

    public function testFormHasMultiFileUploadWithLabelAndHints(): void
    {
        foreach (['de', 'fr', 'en'] as $lang) {
            $body = self::app()->handle('GET', ['lang' => $lang], [])->body;
            self::assertStringContainsString('enctype="multipart/form-data"', $body, $lang);
            self::assertMatchesRegularExpression('/<input type="file" id="f-ausweis" name="ausweis\[\]" multiple required/', $body, $lang);
            self::assertStringContainsString('aria-describedby="hint-ausweis hint-ausweis-privacy"', $body, $lang);
        }
        $de = self::app()->handle('GET', ['lang' => 'de'], [])->body;
        self::assertStringContainsString('höchstens 6 Dateien: Fotos als JPG oder PNG (je bis 5 MB) oder PDF (je bis 2 MB', $de, 'Grenzen stammen aus der Konfiguration');
        $custom = self::app(['APP_MODE' => 'mock', 'UPLOAD_MAX_FILES' => '8'])->handle('GET', [], [])->body;
        self::assertStringContainsString('höchstens 8 Dateien', $custom);
    }

    public function testSubmitWithoutFileShowsUploadError(): void
    {
        $response = self::app()->handle('POST', [], self::validPost(), []);
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Bitte laden Sie die Ausweiskopien der Grundeigentümer hoch.', $response->body);
        self::assertStringContainsString('href="#f-ausweis"', $response->body, 'Fehlerübersicht verlinkt das Feld');
        self::assertStringContainsString('aria-describedby="hint-ausweis hint-ausweis-privacy err-ausweis" aria-invalid="true"', $response->body);
    }

    public function testWrongFileTypeIsRejected(): void
    {
        $response = self::submit(self::validPost(), null, UploadFixtures::files(['<html>kein Bild</html>']));
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Dieses Dateiformat wird nicht angenommen', $response->body);
    }

    public function testOtherErrorsAskToSelectTheFilesAgain(): void
    {
        // Gültige Datei, aber ungültige E-Mail: Browser können Dateien nicht wieder einsetzen.
        $response = self::submit(self::validPost(['email' => 'kaputt']));
        self::assertStringContainsString('Bitte wählen Sie die Dateien erneut aus', $response->body);
        self::assertStringContainsString('id="error-summary"', $response->body);
    }

    public function testSeveralOwnersWithSixFilesAreAccepted(): void
    {
        $files = UploadFixtures::files([
            UploadFixtures::png(), UploadFixtures::jpeg(), UploadFixtures::pdf(),
            UploadFixtures::png(), UploadFixtures::jpeg(), UploadFixtures::pdf(),
        ]);
        self::assertSame(303, self::submit(self::validPost(), null, $files)->status);
    }

    public function testSevenFilesAreTooMany(): void
    {
        $files = UploadFixtures::files(array_fill(0, 7, UploadFixtures::png()));
        $response = self::submit(self::validPost(), null, $files);
        self::assertStringContainsString('Bitte laden Sie höchstens 6 Dateien hoch.', $response->body);
        foreach ($files['tmp_name'] as $tmp) {
            self::assertFileDoesNotExist($tmp, 'auch abgelehnte Dateien bleiben nicht liegen');
        }
    }

    public function testPdfTooBigForMailIsRejectedBeforeSending(): void
    {
        $response = self::submit(self::validPost(), null, UploadFixtures::files(['%PDF-1.4' . str_repeat('x', 3_000_000)]));
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Eine Datei ist zu gross für den Versand (höchstens 2 MB)', $response->body);
    }

    public function testRequestLargerThanPostMaxSizeShowsClearError(): void
    {
        // PHP verwirft dann Felder und Dateien; index.php meldet das als bodyTooLarge.
        $response = self::app()->handle('POST', [], [], [], true);
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Die Dateien sind insgesamt zu gross', $response->body);
        self::assertStringContainsString('höchstens 5 MB pro Datei', $response->body);
    }

    public function testUploadIsCheckedAndDiscardedInMockMode(): void
    {
        $files = UploadFixtures::files([UploadFixtures::png(), UploadFixtures::pdf()]);
        $response = self::submit(self::validPost(), null, $files);
        self::assertSame(303, $response->status);
        foreach ($files['tmp_name'] as $tmp) {
            self::assertFileDoesNotExist($tmp, 'nichts bleibt auf dem Server liegen');
        }
        self::assertStringNotContainsString('ausweis', (string) $response->header('Location'), 'keine Datei- oder Antragsdaten in der Weiterleitung');
    }

    /** Simulierter Versand: merkt sich den Antrag, kann scheitern oder die Bestätigung verlieren. */
    private static function orders(string $outcome = 'ok'): OrderService
    {
        return new class ($outcome) implements OrderService {
            public ?Order $sent = null;

            public function __construct(private string $outcome)
            {
            }

            public function send(Order $order): bool
            {
                if ($this->outcome === 'fail') {
                    throw new GraphException('Graph-Anfrage fehlgeschlagen', 403, 'ErrorAccessDenied');
                }
                $this->sent = $order;

                return $this->outcome !== 'bank_failed';
            }
        };
    }

    private const LIVE_SECRET = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    private static function liveApp(OrderService $orders): App
    {
        return new App(Config::fromArray(['APP_MODE' => 'live', 'APP_SECRET' => self::LIVE_SECRET]), APP_ROOT, self::now(),
            static fn (string $tmp): bool => true, $orders);
    }

    /** Gültiger Antrag für den Live-Modus: Zeitstempel mit dem Live-Schlüssel signiert. */
    private static function livePost(array $override = []): array
    {
        return self::validPost($override + ['ft' => SpamToken::valid(self::now(), self::LIVE_SECRET)]);
    }

    public function testValidOrderIsSentWithAttachments(): void
    {
        $orders = self::orders();
        $files = UploadFixtures::files([UploadFixtures::png(), UploadFixtures::pdf()]);
        $response = self::submit(self::livePost(['lang' => 'fr']), self::liveApp($orders), $files);
        self::assertSame(303, $response->status);
        self::assertSame('Bern 4/1234', $orders->sent->grundbuchblatt);
        self::assertSame(1250000, $orders->sent->betrag);
        self::assertSame('fr', $orders->sent->lang);
        self::assertSame(['ausweis-1.png', 'ausweis-2.pdf'], array_map(static fn ($a) => $a->filename, $orders->sent->attachments));

        parse_str(substr((string) $response->header('Location'), 1), $get);
        $page = self::liveApp($orders)->handle('GET', $get, []);
        self::assertStringContainsString('Nous vous avons envoyé un accusé de réception', $page->body);
    }

    public function testFailedSendShowsFormAgainWithInputAndPhone(): void
    {
        $log = ini_set('error_log', '/dev/null');
        $response = self::submit(self::livePost(), self::liveApp(self::orders('fail')));
        ini_set('error_log', (string) $log);
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Ihr Antrag konnte leider nicht übermittelt werden', $response->body);
        self::assertStringContainsString('+41 58 531 20 00', $response->body);
        self::assertStringContainsString('value="Bern 4/1234"', $response->body, 'Eingaben bleiben erhalten');
        self::assertStringContainsString('href="#f-form"', $response->body);
        self::assertStringContainsString('Bitte wählen Sie die Dateien erneut aus', $response->body);
    }

    public function testFailedConfirmationMailIsShownOnConfirmationPage(): void
    {
        $response = self::submit(self::livePost(), self::liveApp(self::orders('bank_failed')));
        self::assertSame(303, $response->status);
        parse_str(substr((string) $response->header('Location'), 1), $get);
        $page = self::liveApp(self::orders())->handle('GET', $get, []);
        self::assertStringContainsString('Eingangsbestätigung per E-Mail konnte leider nicht versendet werden', $page->body);
    }

    public function testMockModeSendsNothing(): void
    {
        // Im Mock-Modus gibt es keinen OrderService: index.php übergibt null.
        self::assertNull(\Schuldbrief\ServiceFactory::orders(Config::fromArray(['APP_MODE' => 'mock']), APP_ROOT));
    }

    public function testForgedConfirmationIsRejected(): void
    {
        $page = self::app()->handle('GET', ['view' => 'confirmed', 't' => 'abc.def'], []);
        self::assertStringContainsString('Seite nicht mehr gültig', $page->body);
    }

    public function testExpiredConfirmationIsRejected(): void
    {
        $location = (string) self::submit(self::validPost())->header('Location');
        parse_str(substr($location, 1), $get);
        $later = new App(Config::fromArray(['APP_MODE' => 'mock']), APP_ROOT, self::now()->modify('+3 hours'));
        self::assertStringContainsString('Seite nicht mehr gültig', $later->handle('GET', $get, [])->body);
    }

    public function testUnsupportedMethod(): void
    {
        self::assertSame(405, self::app()->handle('DELETE', [], [])->status);
    }

    public function testTestBannerOutsideLiveMode(): void
    {
        self::assertStringContainsString('class="test-banner"', self::app()->handle('GET', [], [])->body);
        $live = self::app(['APP_MODE' => 'live', 'APP_SECRET' => str_repeat('x', 32)]);
        self::assertStringNotContainsString('class="test-banner"', $live->handle('GET', [], [])->body);
    }

    public function testLiveModeWithoutSecretFailsSafely(): void
    {
        $errorLog = ini_set('error_log', '/dev/null');
        $response = self::submit(self::validPost(), self::app(['APP_MODE' => 'live']));
        ini_set('error_log', (string) $errorLog);
        self::assertSame(500, $response->status);
        self::assertStringContainsString('+41 58 531 20 00', $response->body);
        self::assertStringNotContainsString('Anna', $response->body);
    }

    public function testLogLineNeverContainsInput(): void
    {
        $line = App::logLine(new \RuntimeException('Musterweg 3, Anna'));
        self::assertStringStartsWith('[schuldbrief] RuntimeException in AppTest.php:', $line);
        self::assertStringNotContainsString('Anna', $line);
    }
}
