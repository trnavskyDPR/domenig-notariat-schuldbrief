<?php

declare(strict_types=1);

namespace Schuldbrief\Tests;

use PHPUnit\Framework\TestCase;
use Schuldbrief\Http\Health;

final class HealthTest extends TestCase
{
    public function testMetricsPathIsRecognised(): void
    {
        $this->assertTrue(Health::matches('/metrics'));
        $this->assertTrue(Health::matches('/metrics/'));
        $this->assertFalse(Health::matches('/'));
        $this->assertFalse(Health::matches('/metrics2'));
    }

    public function testResponseIsOkAndPrometheusText(): void
    {
        $response = Health::response();

        $this->assertSame(200, $response->status);
        $this->assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('# OK', $response->body);
        $this->assertStringContainsString("notariat_schuldbrief_up 1\n", $response->body);
    }
}
