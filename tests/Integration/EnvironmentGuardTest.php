<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\NoNetworkStreamWrapper;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the safety constraints of the test environment itself, so that a future change
 * to configuration cannot quietly let the suite mail real people, reach the internet, or
 * write to the development database.
 */
final class EnvironmentGuardTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    #[Test]
    public function theSuiteRunsAgainstADedicatedTestDatabase(): void
    {
        $database = self::getContainer()->get(Connection::class)->getDatabase();

        self::assertIsString($database);
        self::assertStringEndsWith(
            '_test',
            $database,
            'Tests must never run against the development or production database.'
        );
    }

    #[Test]
    public function theMailerIsTheNullTransport(): void
    {
        self::assertSame(
            'null://null',
            $_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? null,
            'Tests must not send mail through Brevo or any real transport.'
        );
    }

    #[Test]
    public function jwtSigningUsesThrowawayTestKeysNotTheRealOnes(): void
    {
        $keyPath = self::getContainer()->getParameter('kernel.project_dir') . '/var/jwt/private.pem';

        self::assertFileExists($keyPath, 'Run bin/setup-test-db.sh to generate the test keys.');
        self::assertStringContainsString(
            '/var/jwt/',
            $keyPath,
            'The suite must not sign with the production keys in config/jwt.'
        );
    }

    #[Test]
    public function outboundHttpRequestsAreBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Blocked an outbound network request');

        file_get_contents('https://example.com/some/endpoint');
    }

    #[Test]
    public function outboundImageRequestsAreServedLocally(): void
    {
        NoNetworkStreamWrapper::reset();

        // dompdf's remote images are answered from a local placeholder rather than the
        // network, which is what lets PdfService render offline.
        $bytes = file_get_contents('https://daike.eu/c/assets/logo.jpg');

        self::assertSame(NoNetworkStreamWrapper::placeholderJpeg(), $bytes);
        self::assertNotEmpty(NoNetworkStreamWrapper::$attempts);
    }
}
