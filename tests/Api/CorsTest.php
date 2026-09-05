<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * CorsListener runs at KernelEvents::REQUEST priority 9999, i.e. before the firewall,
 * so CORS headers are present even on responses the firewall rejects.
 */
final class CorsTest extends ApiTestCase
{
    #[Test]
    public function preflightIsAnsweredWithoutAuthentication(): void
    {
        $this->client->request('OPTIONS', '/api/v1/insurance-policies/admin/insurance-clauses', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);

        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
        self::assertNotNull($response->headers->get('Access-Control-Allow-Headers'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertNotNull($response->headers->get('Access-Control-Max-Age'));
    }

    #[Test]
    public function theRequestOriginIsReflectedBack(): void
    {
        $this->request('GET', '/api/v1/form-data/app-config?name=CURRENCY', null, $this->agentToken());

        self::assertSame('*', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        $this->client->request('GET', '/api/v1/form-data/app-config?name=CURRENCY', [], [], [
            'HTTP_ORIGIN' => 'https://propcalc.zastrahovaite.com',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->agentToken(),
        ]);

        self::assertSame(
            'https://propcalc.zastrahovaite.com',
            $this->client->getResponse()->headers->get('Access-Control-Allow-Origin')
        );
    }

    #[Test]
    public function corsHeadersArePresentEvenOnAnUnauthorizedResponse(): void
    {
        $this->client->request('GET', '/api/v1/form-data/app-config', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            'http://localhost:3000',
            $this->client->getResponse()->headers->get('Access-Control-Allow-Origin')
        );
    }
}
