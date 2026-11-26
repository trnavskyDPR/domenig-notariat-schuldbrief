<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Schuldbrief\App;
use Schuldbrief\Config;
use Schuldbrief\Http\Response;

/** Durchstich durch Controller und Templates im Mock-Modus. */
final class AppTest extends TestCase
{
    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26 10:00', new DateTimeZone('Europe/Zurich'));
    }

    private static function app(array $config = ['APP_MODE' => 'mock']): App
    {
        return new App(Config::fromArray($config), APP_ROOT, self::now());
    }

    private static function submit(array $post, ?App $app = null): Response
    {
        return ($app ?? self::app())->handle('POST', [], $post);
    }

    private static function validPost(array $override = []): array
    {
        return $override + ['lang' => 'de'] + OrderValidatorTest::valid();
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
