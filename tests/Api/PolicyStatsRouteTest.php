<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * GET /api/v1/insurance-policies/admin/policies/stats.
 *
 * The route was unreachable: /policies/{id} is declared first and had no requirements,
 * so "stats" matched as an id and view(int $id) died on a TypeError - a 500 pinned in
 * RouteSmokeTest::KNOWN_SERVER_ERRORS. {id} is constrained to digits now, which fixes
 * the shadowing regardless of the order the two methods happen to be declared in.
 */
final class PolicyStatsRouteTest extends ApiTestCase
{
    #[Test]
    public function statsIsReachableAndReturnsTheStatisticsPayload(): void
    {
        $this->request('GET', '/api/v1/insurance-policies/admin/policies/stats', null, $this->adminToken());

        self::assertSame(Response::HTTP_OK, $this->statusCode());

        $body = $this->jsonResponse();

        self::assertArrayHasKey('totalPolicies', $body);
        self::assertArrayHasKey('totalAmount', $body);
        self::assertArrayHasKey('todayPolicies', $body);
        self::assertArrayHasKey('todayAmount', $body);
        self::assertIsInt($body['totalPolicies']);
        self::assertIsInt($body['todayPolicies']);
    }

    #[Test]
    public function statsResolvesToTheStatsControllerNotTheViewController(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);

        $match = $router->match('/api/v1/insurance-policies/admin/policies/stats');

        self::assertSame('api_v1_insurance_policies_admin_stats', $match['_route']);
    }

    #[Test]
    public function aNumericIdStillReachesTheViewController(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);

        $match = $router->match('/api/v1/insurance-policies/admin/policies/42');

        self::assertSame('api_v1_insurance_policies_admin_view', $match['_route']);
        self::assertSame('42', $match['id']);
    }

    /**
     * A non-numeric id no longer reaches view() at all, so it cannot 500 on a TypeError.
     * Any future word-shaped sub-route is safe from the same shadowing.
     */
    #[Test]
    public function aNonNumericIdDoesNotReachTheViewControllerAtAll(): void
    {
        $this->request('GET', '/api/v1/insurance-policies/admin/policies/not-an-id', null, $this->adminToken());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->statusCode());
    }

    #[Test]
    public function anUnknownButNumericIdIsAHandledNotFound(): void
    {
        $this->request('GET', '/api/v1/insurance-policies/admin/policies/999999', null, $this->adminToken());

        self::assertSame(Response::HTTP_NOT_FOUND, $this->statusCode());
        self::assertSame('Insurance policy not found', $this->jsonResponse()['error']);
    }

    #[Test]
    public function statsStillRequiresAnAdminToken(): void
    {
        $this->request('GET', '/api/v1/insurance-policies/admin/policies/stats', null, $this->anonymousToken());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->statusCode());
    }
}
