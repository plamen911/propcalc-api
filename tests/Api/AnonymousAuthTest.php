<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The anonymous-token flow, which is the unusual part of this design: an
 * unauthenticated POST mints a usable JWT.
 */
final class AnonymousAuthTest extends ApiTestCase
{
    #[Test]
    public function itIssuesATokenWithoutAnyCredentials(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');

        self::assertSame(Response::HTTP_OK, $this->statusCode());

        $body = $this->jsonResponse();

        self::assertArrayHasKey('token', $body);
        self::assertIsString($body['token']);
        self::assertCount(3, explode('.', $body['token']), 'A JWT has three dot-separated parts.');
    }

    #[Test]
    public function theIssuedUserCarriesAnonymousAndUserRoles(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');

        $user = $this->jsonResponse()['user'];

        self::assertContains('ROLE_ANONYMOUS', $user['roles']);
        self::assertContains('ROLE_USER', $user['roles'], 'User::getRoles() always appends ROLE_USER.');
        self::assertStringStartsWith('anonymous_', $user['email']);
        self::assertStringEndsWith('@zastrahovaite.com', $user['email']);
    }

    #[Test]
    public function theIssuedTokenIsAcceptedByProtectedRoutes(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');
        $token = $this->jsonResponse()['token'];

        $this->request('GET', '/api/v1/form-data/app-config?name=CURRENCY', null, $token);

        self::assertSame(Response::HTTP_OK, $this->statusCode());
    }

    #[Test]
    public function eachCallIssuesADistinctIdentity(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');
        $first = $this->jsonResponse()['user']['email'];

        $this->request('POST', '/api/v1/auth/anonymous');
        $second = $this->jsonResponse()['user']['email'];

        self::assertNotSame($first, $second);
    }

    /**
     * The retention command ages rows out by created_at, so a minted user must carry
     * one. An undated row is skipped by the purge and would accumulate for ever.
     */
    #[Test]
    public function aMintedAnonymousUserIsStamped(): void
    {
        $this->request('POST', '/api/v1/auth/anonymous');
        $email = $this->jsonResponse()['user']['email'];

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertNotNull($user);
        self::assertNotNull($user->getCreatedAt(), 'created_at drives app:purge-anonymous-users.');
    }

    /**
     * Characterization test: every unauthenticated call still inserts a row into the
     * user table. Left deliberately: the agreed mitigation is retention, not
     * prevention, because reuse or a row-less token would change what the public
     * calculator gets. app:purge-anonymous-users ages the rows out - see
     * tests/Integration/Command/PurgeAnonymousUsersTest.php - and this test guards the
     * mint path itself against changing under it. There is still no rate limiting.
     */
    #[Test]
    public function everyCallPersistsANewUserRow_KNOWN_GAP(): void
    {
        $countUsers = fn (): int => (int) $this->em()
            ->createQuery('SELECT COUNT(u.id) FROM ' . User::class . ' u')
            ->getSingleScalarResult();

        $before = $countUsers();

        $this->request('POST', '/api/v1/auth/anonymous');
        $this->request('POST', '/api/v1/auth/anonymous');
        $this->request('POST', '/api/v1/auth/anonymous');

        self::assertSame($before + 3, $countUsers());
    }
}
