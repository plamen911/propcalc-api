<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * The security boundary defined by config/packages/security.yaml.
 *
 * Three access_control rules grant PUBLIC_ACCESS; the admin prefixes require ROLE_ADMIN;
 * everything else under ^/api requires IS_AUTHENTICATED_FULLY through the stateless JWT
 * firewall.
 *
 * The authorization gaps these tests used to pin as *_KNOWN_GAP are fixed: a
 * ROLE_ANONYMOUS token no longer reaches admin data, /tariff/pdf/email is no longer
 * public, and the dead ^/api/v1/auth-test/public rule is gone. The one deliberate
 * exception - the tariff catalogue the public calculator reads - is asserted below as
 * intended behaviour rather than as a gap.
 */
final class SecurityBoundaryTest extends ApiTestCase
{
    // -----------------------------------------------------------------------
    // The three PUBLIC_ACCESS rules
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
     * The public rule is anchored to /tariff/pdf exactly, so the /email variant - which
     * makes the service send mail with an attachment to an arbitrary recipient - is no
     * longer reachable without a token.
     */
    #[Test]
    public function tariffPdfEmailRequiresAToken(): void
    {
        $this->request('POST', '/api/v1/tariff/pdf/email', ['recipientEmail' => 'someone@example.test']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
    }

    /**
     * The calculator sends the quote PDF by mail with the anonymous token it already
     * holds, so requiring a token must not require an ADMIN one.
     */
    #[Test]
    public function tariffPdfEmailStillWorksForTheCalculatorsAnonymousToken(): void
    {
        $this->request(
            'POST',
            '/api/v1/tariff/pdf/email',
            ['recipientEmail' => 'someone@example.test'],
            $this->anonymousToken()
        );

        self::assertNotSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
        self::assertNotSame(Response::HTTP_FORBIDDEN, $this->statusCode());
    }

    /**
     * ^/api/v1/auth-test/public granted public access to a path with no route behind it.
     *
     * The rule is deleted. This cannot be asserted over HTTP: RouterListener runs at
     * priority 32 and the firewall at 8, so a path with no route 404s during routing
     * either way. The assertion is therefore on the configuration itself - a rule
     * matching a path that has no route is dead weight that a future route could
     * silently inherit.
     */
    #[Test]
    public function theDeadAuthTestRuleIsGone(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');
        $paths = array_column($config['security']['access_control'], 'path');

        self::assertNotEmpty($paths);
        foreach ($paths as $path) {
            self::assertStringNotContainsString('auth-test', $path, 'Dead access_control rule is back.');
        }

        // And the path itself still has no route behind it.
        $this->request('GET', '/api/v1/auth-test/public');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->statusCode());
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
     * UserManagementController was the only controller carrying #[IsGranted('ROLE_ADMIN')]
     * before the admin surface was locked down; every admin controller carries one now.
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
     * The admin surface, which is spread over three unrelated URL prefixes. Anybody can
     * mint a ROLE_ANONYMOUS token unauthenticated at /api/v1/auth/anonymous, so a valid
     * token must not be enough to reach any of these.
     *
     * The tariff catalogue read is deliberately absent: see
     * theTariffCatalogueStaysReadableWithAnAnonymousToken() below.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function adminRoutes(): iterable
    {
        yield 'tariff preset clauses' => ['GET', '/api/v1/insurance-policies/admin/tariff-preset-clauses'];
        yield 'insurance clauses' => ['GET', '/api/v1/insurance-policies/admin/insurance-clauses'];
        yield 'app configs' => ['GET', '/api/v1/app-configs/admin'];
        yield 'promotional codes' => ['GET', '/api/v1/admin/promotional-codes'];
        yield 'policy list' => ['GET', '/api/v1/insurance-policies/admin/policies'];
        yield 'user list' => ['GET', '/api/v1/admin/users'];
        yield 'profile' => ['GET', '/api/v1/admin/profile'];
    }

    #[DataProvider('adminRoutes')]
    #[Test]
    public function adminRoutesRejectAnAnonymousToken(string $method, string $uri): void
    {
        $this->request($method, $uri, null, $this->anonymousToken());

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->statusCode(),
            sprintf('%s %s must not be reachable with a ROLE_ANONYMOUS token.', $method, $uri)
        );
    }

    /**
     * ROLE_AGENT and ROLE_OFFICE users exist and cannot obtain a token from
     * /admin/auth/login today, but a token signed for one must not open the admin API
     * either - that login check is not the boundary, this is.
     */
    #[DataProvider('adminRoutes')]
    #[Test]
    public function adminRoutesRejectAValidNonAdminToken(string $method, string $uri): void
    {
        $this->request($method, $uri, null, $this->agentToken());

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->statusCode(),
            sprintf('%s %s must not be reachable with a ROLE_AGENT token.', $method, $uri)
        );
    }

    #[DataProvider('adminRoutes')]
    #[Test]
    public function adminRoutesAcceptAnAdminToken(string $method, string $uri): void
    {
        $this->request($method, $uri, null, $this->adminToken());

        self::assertSame(
            Response::HTTP_OK,
            $this->statusCode(),
            sprintf('%s %s must stay open to ROLE_ADMIN.', $method, $uri)
        );
    }

    // -----------------------------------------------------------------------
    // The one deliberate exception: the public calculator's catalogue read
    // -----------------------------------------------------------------------

    /**
     * propcalc-client's covered-risks step reads the tariff catalogue with the anonymous
     * token it mints on page load. The payload is the public price list, so this read -
     * and only this read - stays open to any authenticated caller. Locking it would take
     * the live quote flow down.
     */
    #[Test]
    public function theTariffCatalogueStaysReadableWithAnAnonymousToken(): void
    {
        $this->request('GET', '/api/v1/insurance-policies/admin/tariff-presets', null, $this->anonymousToken());

        self::assertSame(Response::HTTP_OK, $this->statusCode());
    }

    /**
     * The same catalogue under a name that does not read as admin. The client can move
     * to this route whenever it is redeployed; the exception above goes with it.
     */
    #[Test]
    public function theCatalogueIsAlsoServedUnderAPublicFacingName(): void
    {
        $this->request('GET', '/api/v1/form-data/tariff-presets', null, $this->anonymousToken());

        self::assertSame(Response::HTTP_OK, $this->statusCode());
    }

    #[Test]
    public function bothCatalogueRoutesReturnTheSamePayload(): void
    {
        $query = '?area_sq_meters=150';

        $this->request('GET', '/api/v1/insurance-policies/admin/tariff-presets' . $query, null, $this->anonymousToken());
        $viaAdminPath = $this->jsonResponse();

        $this->request('GET', '/api/v1/form-data/tariff-presets' . $query, null, $this->anonymousToken());

        self::assertSame($viaAdminPath, $this->jsonResponse());
    }

    #[Test]
    public function theCatalogueRoutesStillRequireAToken(): void
    {
        $this->request('GET', '/api/v1/form-data/tariff-presets');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->statusCode());
    }

    /**
     * Reading the catalogue is open; changing it is not. The access_control exception is
     * anchored and method-scoped, and each write carries its own #[IsGranted].
     *
     * @return iterable<string, array{string, string}>
     */
    public static function tariffPresetWrites(): iterable
    {
        yield 'create preset' => ['POST', '/api/v1/insurance-policies/admin/tariff-presets'];
        yield 'update preset' => ['PUT', '/api/v1/insurance-policies/admin/tariff-presets/1'];
        yield 'delete preset' => ['DELETE', '/api/v1/insurance-policies/admin/tariff-presets/1'];
        yield 'update preset clause' => ['PUT', '/api/v1/insurance-policies/admin/tariff-preset-clauses/1'];
    }

    #[DataProvider('tariffPresetWrites')]
    #[Test]
    public function tariffPresetWritesRejectAnAnonymousToken(string $method, string $uri): void
    {
        $this->request($method, $uri, ['name' => 'Injected'], $this->anonymousToken());

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $this->statusCode(),
            sprintf('%s %s must not be reachable with a ROLE_ANONYMOUS token.', $method, $uri)
        );
    }
}
