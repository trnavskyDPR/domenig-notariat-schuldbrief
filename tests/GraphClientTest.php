<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\TestCase;
use Schuldbrief\App;
use Schuldbrief\Graph\GraphClient;
use Schuldbrief\Graph\GraphException;
use Schuldbrief\Http\HttpResult;
use Schuldbrief\Http\NetworkException;
use RuntimeException;

/** Anmeldung, Wiederholung und Fehlercodes – unverändert aus dem Beglaubigungstool übernommen. */
final class GraphClientTest extends TestCase
{
    public function testRetriesOnceWhenThrottled(): void
    {
        $sleeps = [];
        $http = new FakeHttp([
            new HttpResult(200, '{"access_token":"tok","expires_in":3600}'),
            new HttpResult(429, '{"error":{"code":"ApplicationThrottled"}}'),
            new HttpResult(202, ''),
        ]);
        $graph = new GraphClient($http, 't', 'c', 's', static function (int $s) use (&$sleeps): void {
            $sleeps[] = $s;
        });
        self::assertSame([], $graph->post('/users/x/sendMail', []));
        self::assertSame([1], $sleeps);

        $http = new FakeHttp([
            new HttpResult(200, '{"access_token":"tok","expires_in":3600}'),
            new HttpResult(503, ''),
            new HttpResult(503, ''),
        ]);
        $graph = new GraphClient($http, 't', 'c', 's', static fn (int $s) => null);
        $this->expectException(GraphException::class);
        $graph->post('/x', []);
    }

    public function testLogsInAgainOnceAfter401(): void
    {
        $http = new FakeHttp([
            new HttpResult(200, '{"access_token":"alt","expires_in":3600}'),
            new HttpResult(401, '{"error":{"code":"InvalidAuthenticationToken"}}'),
            new HttpResult(200, '{"access_token":"neu","expires_in":3600}'),
            new HttpResult(202, ''),
        ]);
        (new GraphClient($http, 't', 'c', 's'))->post('/x', []);
        self::assertContains('Authorization: Bearer neu', $http->requests[3]['headers']);
    }

    public function testWrongSecretGivesReadableLogLine(): void
    {
        $http = new FakeHttp([new HttpResult(401, '{"error":"invalid_client","error_description":"AADSTS7000215: Invalid client secret"}')]);
        try {
            (new GraphClient($http, 't', 'c', 'falsch'))->post('/x', []);
            self::fail('keine Ausnahme');
        } catch (GraphException $e) {
            self::assertSame('[schuldbrief] Schuldbrief\Graph\GraphException in GraphClient.php:' . $e->getLine() . ' – Graph HTTP 401 invalid_client', App::logLine($e));
        }
        self::assertStringNotContainsString('falsch', $http->requests[0]['url'], 'Schlüssel nie in der URL');
    }

    public function testErrorCodeIsSanitized(): void
    {
        self::assertSame('ErrorAccessDenied', GraphException::codeFrom('{"error":{"code":"ErrorAccessDenied"}}'));
        self::assertSame('scriptalert1script', GraphException::codeFrom('{"error":{"code":"<script>alert(1)</script>"}}'));
        self::assertSame('', GraphException::codeFrom('kein JSON'));
    }

    public function testNetworkErrorsAreReadableInTheLogOtherMessagesNot(): void
    {
        self::assertStringContainsString('Netzwerkfehler (curl 28)', App::logLine(new NetworkException('Netzwerkfehler (curl 28)')));
        self::assertStringNotContainsString('geheim', App::logLine(new RuntimeException('geheim')));
    }
}
