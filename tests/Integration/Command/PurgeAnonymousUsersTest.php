<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The retention command for the rows POST /api/v1/auth/anonymous leaves behind.
 *
 * The endpoint is public and inserts a row per call, so growth is unbounded; this is
 * the agreed mitigation. It must delete only aged-out anonymous users - never a real
 * account, never a recent anonymous one whose token is still inside the 1h JWT ttl.
 */
final class PurgeAnonymousUsersTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $application = new Application(self::$kernel);
        $this->tester = new CommandTester($application->find('app:purge-anonymous-users'));
    }

    /** @param list<string> $roles */
    private function makeUser(array $roles, ?\DateTimeImmutable $createdAt): User
    {
        $user = new User();
        $user->setEmail(sprintf('purge_%s@example.test', bin2hex(random_bytes(6))));
        $user->setRoles($roles);
        $user->setPassword('irrelevant');
        $user->setCreatedAt($createdAt);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function stillExists(User $user): bool
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->find($user->getId()) !== null;
    }

    #[Test]
    public function aNewUserRecordsItsCreationTime(): void
    {
        $user = new User();

        self::assertNotNull($user->getCreatedAt(), 'User::__construct() stamps created_at.');
    }

    #[Test]
    public function itDeletesAnonymousUsersPastTheRetentionWindow(): void
    {
        $old = $this->makeUser(['ROLE_ANONYMOUS'], new \DateTimeImmutable('-30 days'));

        $this->tester->execute(['--older-than' => '7']);

        self::assertSame(Command::SUCCESS, $this->tester->getStatusCode());
        self::assertFalse($this->stillExists($old));
    }

    #[Test]
    public function itKeepsAnonymousUsersInsideTheRetentionWindow(): void
    {
        // A token minted minutes ago is still valid for an hour; its row must survive.
        $fresh = $this->makeUser(['ROLE_ANONYMOUS'], new \DateTimeImmutable('-10 minutes'));

        $this->tester->execute(['--older-than' => '7']);

        self::assertTrue($this->stillExists($fresh));
    }

    #[Test]
    public function itNeverTouchesARealAccountHoweverOld(): void
    {
        $admin = $this->makeUser(['ROLE_ADMIN'], new \DateTimeImmutable('-5 years'));
        $agent = $this->makeUser(['ROLE_AGENT'], new \DateTimeImmutable('-5 years'));

        $this->tester->execute(['--older-than' => '1']);

        self::assertTrue($this->stillExists($admin));
        self::assertTrue($this->stillExists($agent));
    }

    #[Test]
    public function undatedRowsAreKeptUnlessAskedForExplicitly(): void
    {
        // Rows that predate the created_at column have unknown age.
        $undated = $this->makeUser(['ROLE_ANONYMOUS'], null);

        $this->tester->execute(['--older-than' => '7']);

        self::assertTrue($this->stillExists($undated));
    }

    #[Test]
    public function undatedRowsAreDeletedWithIncludeUndated(): void
    {
        $undated = $this->makeUser(['ROLE_ANONYMOUS'], null);

        $this->tester->execute(['--older-than' => '7', '--include-undated' => true]);

        self::assertFalse($this->stillExists($undated));
    }

    #[Test]
    public function dryRunReportsWithoutDeleting(): void
    {
        $old = $this->makeUser(['ROLE_ANONYMOUS'], new \DateTimeImmutable('-30 days'));

        $this->tester->execute(['--older-than' => '7', '--dry-run' => true]);

        self::assertTrue($this->stillExists($old));
        self::assertStringContainsString('Nothing was changed', $this->tester->getDisplay());
    }

    #[Test]
    public function aRetentionWindowShorterThanADayIsRefused(): void
    {
        // The JWT ttl is one hour; purging inside it would invalidate live tokens.
        $this->tester->execute(['--older-than' => '0']);

        self::assertSame(Command::INVALID, $this->tester->getStatusCode());
    }
}
