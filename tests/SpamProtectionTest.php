<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Schuldbrief\App;
use Schuldbrief\Config;
use Schuldbrief\Security\RateLimiter;
use Schuldbrief\Security\Signer;
use Schuldbrief\Security\SpamGuard;

/** Schritt d: Fallenfeld, Mindest-Ausfüllzeit, Limit pro IP, IP hinter dem Gateway. */
final class SpamProtectionTest extends TestCase
{
    private const SECRET = 'test-schluessel-mit-mindestens-32-zeichen';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/schuldbrief-rate-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-26 10:00', new DateTimeZone('Europe/Zurich'));
    }

    private static function app(array $config = ['APP_MODE' => 'mock']): App
    {
        return new App(Config::fromArray($config), APP_ROOT, self::now(), static fn (string $tmp): bool => true);
    }

    public function testSpamGuard(): void
    {
        $signer = new Signer(self::SECRET);
        $rendered = (new SpamGuard($signer, self::now()->modify('-1 minute')))->formToken();
        $guard = new SpamGuard($signer, self::now());

        self::assertNull($guard->check(['ft' => $rendered, 'website' => '']), 'Mensch nach einer Minute');
        self::assertSame('error.spam', $guard->check(['ft' => $rendered, 'website' => 'http://spam.example']), 'Fallenfeld ausgefüllt');
        self::assertSame('error.spam', $guard->check(['ft' => $guard->formToken()]), 'sofort abgesendet');
        self::assertSame('error.form_expired', $guard->check([]), 'ohne Zeitstempel');
        self::assertSame('error.form_expired', $guard->check(['ft' => 'gefaelscht.xyz']));
        $old = (new SpamGuard($signer, self::now()->modify('-3 days')))->formToken();
        self::assertSame('error.form_expired', $guard->check(['ft' => $old]), 'älter als 2 Tage');
    }

    public function testRateLimiterPerIpAndHour(): void
    {
        $limiter = new RateLimiter($this->dir, 'k', 3);
        $t = time(); // echte Zeit: die zufällige Aufräumroutine vergleicht mit dem Dateidatum
        self::assertTrue($limiter->hit('203.0.113.7', $t));
        self::assertTrue($limiter->hit('203.0.113.7', $t + 1));
        self::assertTrue($limiter->hit('203.0.113.7', $t + 2));
        self::assertFalse($limiter->hit('203.0.113.7', $t + 3), '4. Versuch in der Stunde');
        self::assertTrue($limiter->hit('198.51.100.1', $t + 3), 'andere IP ist unabhängig');
        self::assertTrue($limiter->hit('203.0.113.7', $t + 3601), 'nach einer Stunde wieder frei');
    }

    public function testRateLimiterStoresNoReadableIp(): void
    {
        (new RateLimiter($this->dir, 'k', 3))->hit('203.0.113.7', 1_800_000_000);
        $files = glob($this->dir . '/*');
        self::assertCount(1, $files);
        self::assertStringNotContainsString('203.0.113.7', basename($files[0]));
        self::assertStringNotContainsString('203.0.113.7', (string) file_get_contents($files[0]));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', basename($files[0]));
    }

    public function testZeroDisablesTheLimit(): void
    {
        $limiter = new RateLimiter($this->dir, 'k', 0);
        for ($i = 0; $i < 20; $i++) {
            self::assertTrue($limiter->hit('203.0.113.7', 1_800_000_000 + $i));
        }
    }

    public function testClientIpBehindGateway(): void
    {
        self::assertSame('203.0.113.7', App::clientIp(['HTTP_X_FORWARDED_FOR' => '10.0.0.1, 203.0.113.7', 'REMOTE_ADDR' => '172.16.0.2']));
        // Links kann der Besucher selbst etwas eintragen, rechts steht der Eintrag des eigenen Proxys.
        self::assertSame('198.51.100.9', App::clientIp(['HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.9']));
        self::assertSame('198.51.100.9', App::clientIp(['HTTP_X_FORWARDED_FOR' => 'unsinn', 'REMOTE_ADDR' => '198.51.100.9']));
        self::assertSame('', App::clientIp(['REMOTE_ADDR' => '172.16.0.2']), 'nur Gateway-Adresse: kein gemeinsames Limit');
        self::assertSame('', App::clientIp([]));
    }

    public function testFormHasHoneypotAndTimestamp(): void
    {
        $form = self::app()->handle('GET', [], [])->body;
        self::assertMatchesRegularExpression('/<input type="hidden" name="ft" value="[A-Za-z0-9_.-]+">/', $form);
        self::assertStringContainsString('name="website" value="" tabindex="-1" autocomplete="off"', $form);
        self::assertStringContainsString('<div class="hp" aria-hidden="true">', $form);
    }

    public function testHoneypotIsRejectedAndInputKept(): void
    {
        $post = ['lang' => 'de', 'website' => 'spam', 'ft' => SpamToken::valid(self::now())] + OrderValidatorTest::valid();
        $response = self::app()->handle('POST', [], $post, ['ausweis' => UploadFixtures::files([UploadFixtures::png()])]);
        self::assertSame(200, $response->status);
        self::assertStringContainsString('Ihr Antrag konnte nicht verarbeitet werden', $response->body);
        self::assertStringContainsString('href="#f-form"', $response->body);
        self::assertStringContainsString('value="Bern 4/1234"', $response->body, 'Eingaben bleiben erhalten');
    }

    public function testExpiredFormKeepsInput(): void
    {
        $post = ['lang' => 'de', 'ft' => 'abgelaufen.x'] + OrderValidatorTest::valid();
        $page = self::app()->handle('POST', [], $post);
        self::assertStringContainsString('Das Formular war zu lange geöffnet', $page->body);
        self::assertStringContainsString('value="anna.muster@bank.ch"', $page->body);
    }

    public function testAppStopsAfterLimit(): void
    {
        $app = self::app(['APP_MODE' => 'mock', 'RATE_LIMIT_PER_HOUR' => '2']);
        $server = ['HTTP_X_FORWARDED_FOR' => '198.51.100.' . random_int(1, 254)];
        $post = ['lang' => 'de', 'ft' => SpamToken::valid(self::now())];
        $app->handle('POST', [], $post, [], false, $server);
        $app->handle('POST', [], $post, [], false, $server);
        $third = $app->handle('POST', [], $post, [], false, $server);
        self::assertStringContainsString('zu viele Anträge in kurzer Zeit', $third->body);
    }
}
