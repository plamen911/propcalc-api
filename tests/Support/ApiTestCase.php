<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for tests that drive the application over HTTP.
 *
 * Users are created for real and signed with the real Lexik JWT manager, so the tests
 * exercise the actual firewall rather than a stubbed security context. Every write is
 * rolled back after the test by dama/doctrine-test-bundle.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Leave exception catching on, so the kernel turns security exceptions into the
        // same HTTP responses production returns (403 rather than a thrown exception).
    }

    protected function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(array $roles, ?string $email = null): User
    {
        $user = new User();
        $user->setEmail($email ?? sprintf('user_%s@example.test', bin2hex(random_bytes(6))));
        $user->setRoles($roles);
        $user->setFirstName('Test');
        $user->setLastName('User');

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'test-password'));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function tokenFor(User $user): string
    {
        return self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    protected function adminToken(): string
    {
        return $this->tokenFor($this->createUser(['ROLE_ADMIN']));
    }

    protected function agentToken(): string
    {
        return $this->tokenFor($this->createUser(['ROLE_AGENT']));
    }

    /** A token of the kind anyone can mint unauthenticated at /api/v1/auth/anonymous. */
    protected function anonymousToken(): string
    {
        return $this->tokenFor($this->createUser(['ROLE_ANONYMOUS']));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function request(
        string $method,
        string $uri,
        ?array $payload = null,
        ?string $token = null
    ): void {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $server,
            $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<mixed>
     */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertJson($content, 'Response body was not valid JSON: ' . substr($content, 0, 500));

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function statusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }
}
