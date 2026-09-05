<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security boundary defined by config/packages/security.yaml.
 *
 * Four access_control rules grant PUBLIC_ACCESS; everything else under ^/api requires
 * IS_AUTHENTICATED_FULLY through the stateless JWT firewall.
 *
 * Several tests here are named *_KNOWN_GAP. They assert what the application currently
 * does, not what it arguably should do, so that the suite is green and any change in
 * behaviour is caught. The gaps are reported separately; no production code was changed.
 */
final class SecurityBoundaryTest extends ApiTestCase
{
    // -----------------------------------------------------------------------
    // The four PUBLIC_ACCESS rules
    // -----------------------------------------------------------------------

    #[Test]
    public function anonymousAuthIsReachableWithoutAToken(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');

        self::assertSame(Response::HTTP_OK, $this->statusCode());
    }

    #[Test]
    public function adminLoginIsReachableWithoutAToken(): void
    {
        // Note the field is "username", not "email".
        $this->request('POST', '/api/v1/admin/auth/login', ['username' => 'nobody@example.test', 'password' => 'wrong']);

        // Reachable means the firewall let it through to the controller; the controller
        // then answers 401 for an unknown user.
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
        self::assertSame('Невалидни данни за вход', $this->jsonResponse()['message']);
    }

    #[Test]
    public function tariffPdfIsReachableWithoutAToken(): void
    {
        $this->request('POST', '/api/v1/tariff/pdf', ['selectedTariff' => []]);

        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
    }

    /**
     * The ^/api/v1/tariff/pdf rule is a PREFIX, so it also exposes the /email variant:
     * unauthenticated mail sending, with an attachment, to an arbitrary recipient.
     * Reported as a finding.
     */
    #[Test]
    public function tariffPdfEmailIsAlsoPublicBecauseTheRuleIsAPrefix_KNOWN_GAP(): void
    {
        $this->request('POST', '/api/v1/tariff/pdf/email', ['recipientEmail' => 'someone@example.test']);

        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
    }

    /**
     * The fourth PUBLIC_ACCESS rule, ^/api/v1/auth-test/public, matches no route at all -
     * "auth-test" appears nowhere else in the codebase. It is dead configuration, so the
     * path 404s rather than 401s. Reported as a finding.
     */
    #[Test]
    public function theAuthTestPublicRuleIsDeadConfiguration_KNOWN_GAP(): void
    {
        $this->request('GET', '/api/v1/auth-test/public');

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->statusCode(),
            'The rule grants public access to a path that has no route.'
        );
    }

    // -----------------------------------------------------------------------
    // Everything else requires a token
    // -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedRoutes(): iterable
    {
        yield 'settlement autocomplete' => ['GET', '/api/v1/settlements?query=sof'];
        yield 'form data initial' => ['GET', '/api/v1/form-data/initial-data'];
        yield 'form data settlements' => ['GET', '/api/v1/form-data/settlements'];
        yield 'form data app config' => ['GET', '/api/v1/form-data/app-config'];
        yield 'create insurance policy' => ['POST', '/api/v1/insurance-policies'];
        yield 'validate promo code' => ['POST', '/api/v1/promotional-codes/validate'];
        yield 'admin tariff presets' => ['GET', '/api/v1/insurance-policies/admin/tariff-presets'];
        yield 'admin insurance clauses' => ['GET', '/api/v1/insurance-policies/admin/insurance-clauses'];
        yield 'admin app configs' => ['GET', '/api/v1/app-configs/admin'];
        yield 'admin promotional codes' => ['GET', '/api/v1/admin/promotional-codes'];
        yield 'admin policy list' => ['GET', '/api/v1/insurance-policies/admin/policies'];
        yield 'admin user list' => ['GET', '/api/v1/admin/users'];
        yield 'admin profile' => ['GET', '/api/v1/admin/profile'];
        yield 'admin user management' => ['GET', '/api/v1/admin/user-management/1'];
    }

    #[DataProvider('protectedRoutes')]
    #[Test]
    public function protectedRoutesRejectRequestsWithoutAToken(string $method, string $uri): void
    {
        $this->request($method, $uri);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->statusCode(), $method . ' ' . $uri);
    }

    #[Test]
    public function aMalformedTokenIsRejected(): void
    {
        $this->request('GET', '/api/v1/form-data/app-config?name=CURRENCY', null, 'not-a-real-jwt');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
    }

    #[Test]
    public function aValidTokenIsAccepted(): void
    {
        $this->request('GET', '/api/v1/form-data/app-config?name=CURRENCY', null, $this->agentToken());

        self::assertSame(Response::HTTP_OK, $this->statusCode());
        self::assertSame(['name' => 'CURRENCY', 'value' => '€'], $this->jsonResponse());
    }

    // -----------------------------------------------------------------------
    // Role enforcement
    // -----------------------------------------------------------------------

    /**
     * UserManagementController is the only controller in the application carrying
     * #[IsGranted('ROLE_ADMIN')]. This is the one genuine role boundary.
     */
    #[Test]
    public function userManagementRejectsAValidNonAdminToken(): void
    {
        $this->request('GET', '/api/v1/admin/user-management/1', null, $this->agentToken());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->statusCode());
    }

    #[Test]
    public function userManagementRejectsAnAnonymousToken(): void
    {
        $this->request('GET', '/api/v1/admin/user-management/1', null, $this->anonymousToken());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->statusCode());
    }

    #[Test]
    public function userManagementAcceptsAnAdminToken(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);
        $this->request('GET', '/api/v1/admin/user-management/' . $admin->getId(), null, $this->tokenFor($admin));

        self::assertSame(Response::HTTP_OK, $this->statusCode());
    }

    /**
     * Every other /admin route relies on IS_AUTHENTICATED_FULLY alone. A ROLE_ANONYMOUS
     * token - which anybody can mint unauthenticated at /api/v1/auth/anonymous - is
     * therefore enough to reach admin data. Reported as a finding.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function adminRoutesWithoutRoleChecks(): iterable
    {
        yield 'tariff presets' => ['GET', '/api/v1/insurance-policies/admin/tariff-presets'];
        yield 'tariff preset clauses' => ['GET', '/api/v1/insurance-policies/admin/tariff-preset-clauses'];
        yield 'insurance clauses' => ['GET', '/api/v1/insurance-policies/admin/insurance-clauses'];
        yield 'app configs' => ['GET', '/api/v1/app-configs/admin'];
        yield 'promotional codes' => ['GET', '/api/v1/admin/promotional-codes'];
        yield 'policy list' => ['GET', '/api/v1/insurance-policies/admin/policies'];
        yield 'user list' => ['GET', '/api/v1/admin/users'];
    }

    #[DataProvider('adminRoutesWithoutRoleChecks')]
    #[Test]
    public function adminRoutesAcceptAnAnonymousToken_KNOWN_GAP(string $method, string $uri): void
    {
        $this->request($method, $uri, null, $this->anonymousToken());

        self::assertSame(
            Response::HTTP_OK,
            $this->statusCode(),
            sprintf('%s %s is reachable with a ROLE_ANONYMOUS token.', $method, $uri)
        );
    }
}
