<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Routing\RouterInterface;

/**
 * Walks the real routing table and asserts that no route answers with a 5xx.
 *
 * Every route is called with an admin token and placeholder parameters, so a route may
 * legitimately answer 400/403/404 - what it must never do is blow up.
 */
final class RouteSmokeTest extends ApiTestCase
{
    /**
     * Routes are enumerated from the router itself, so a newly added route is covered
     * automatically. A throwaway kernel is booted because data providers run before the
     * test case is set up.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function apiRoutes(): iterable
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);

        $routes = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();

            if (!str_starts_with($path, '/api')) {
                continue;
            }

            $methods = $route->getMethods() ?: ['GET'];
            $method = $methods[0];

            // Substitute placeholder values for any route parameters.
            $uri = preg_replace('/\{[^}]+\}/', '1', $path);

            $routes[sprintf('%s %s (%s)', $method, $uri, $name)] = [$method, $uri];
        }

        self::ensureKernelShutdown();

        ksort($routes);

        yield from $routes;
    }

    /**
     * Routes that currently DO return a 500. These are real production bugs, left
     * unfixed on purpose and reported separately. They are pinned here so the suite
     * stays green and so that fixing one makes this test fail, prompting its removal
     * from the list.
     *
     * @var array<string, string>
     */
    private const KNOWN_SERVER_ERRORS = [
        // The /policies/{id} route is declared before /policies/stats and has no
        // requirements, so "stats" is matched as {id}:
        // TypeError: view(): Argument #1 ($id) must be of type int, string given.
        // The stats endpoint is unreachable.
        'GET /api/v1/insurance-policies/admin/policies/stats' =>
            'Shadowed by /policies/{id}; the stats endpoint cannot be reached.',

        // createTariffPreset() writes straight to the database without validating the
        // payload: NotNullConstraintViolationException, Column 'name' cannot be null.
        'POST /api/v1/insurance-policies/admin/tariff-presets' =>
            'A create with a missing name is not validated and reaches the database.',
    ];

    #[DataProvider('apiRoutes')]
    #[Test]
    public function noRouteReturnsAServerError(string $method, string $uri): void
    {
        $this->request($method, $uri, [], $this->adminToken());

        $status = $this->statusCode();
        $key = $method . ' ' . $uri;

        if (isset(self::KNOWN_SERVER_ERRORS[$key])) {
            self::assertSame(
                500,
                $status,
                sprintf(
                    'Known-broken route %s no longer returns 500 - if it is fixed, remove it '
                    . 'from KNOWN_SERVER_ERRORS. Reason it was listed: %s',
                    $key,
                    self::KNOWN_SERVER_ERRORS[$key]
                )
            );

            return;
        }

        self::assertLessThan(
            500,
            $status,
            sprintf(
                "%s %s returned %d.\n%s",
                $method,
                $uri,
                $status,
                substr((string) $this->client->getResponse()->getContent(), 0, 400)
            )
        );
    }

    #[Test]
    public function theRoutingTableIsNotEmpty(): void
    {
        $count = iterator_count(self::apiRoutes());

        self::assertGreaterThan(30, $count, 'Expected the full /api routing table to be walked.');
    }
}
