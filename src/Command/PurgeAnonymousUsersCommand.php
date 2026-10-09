<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PromotionalCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes aged-out ROLE_ANONYMOUS users.
 *
 * POST /api/v1/auth/anonymous is public and inserts a row per call, so the table grows
 * without bound. Nothing references an anonymous user - InsurancePolicy has no user
 * association at all - so the rows are only useful for as long as the token signed
 * against them is valid.
 *
 * Retention must therefore stay comfortably above the JWT ttl (3600s, see
 * config/packages/lexik_jwt_authentication.yaml). Deleting a user whose token is still
 * live makes that token fail to resolve its user and start answering 401; the client's
 * response interceptor re-mints on 401, so the visible effect is nil, but there is no
 * reason to run this close to the ttl.
 */
#[AsCommand(
    name: 'app:purge-anonymous-users',
    description: 'Deletes ROLE_ANONYMOUS users older than the retention window',
)]
class PurgeAnonymousUsersCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 7;
    private const BATCH_SIZE = 500;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Retention window in days. Rows created within it are kept.',
                (string) self::DEFAULT_RETENTION_DAYS
            )
            ->addOption(
                'include-undated',
                null,
                InputOption::VALUE_NONE,
                'Also delete rows with no created_at - anonymous users inserted before '
                . 'that column existed. Their age is unknown, so this is opt-in.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would be deleted and change nothing.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('older-than');
        if ($days < 1) {
            $io->error('--older-than must be at least 1 day; the JWT ttl is one hour.');

            return Command::INVALID;
        }

        $includeUndated = (bool) $input->getOption('include-undated');
        $dryRun = (bool) $input->getOption('dry-run');
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));

        $io->title('Purging anonymous users');
        $io->text(sprintf('Cutoff: %s (%d days)', $cutoff->format('Y-m-d H:i:s'), $days));

        $ids = $this->findPurgeableIds($cutoff, $includeUndated);

        if ($ids === []) {
            $io->success('Nothing to purge.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('%d anonymous users would be deleted. Nothing was changed.', count($ids)));

            return Command::SUCCESS;
        }

        $deleted = 0;
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $deleted += (int) $this->entityManager
                ->createQuery('DELETE FROM ' . User::class . ' u WHERE u.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->execute();
        }

        $io->success(sprintf('Deleted %d anonymous users.', $deleted));

        return Command::SUCCESS;
    }

    /**
     * Anonymous users past the cutoff that nothing points at.
     *
     * Roles are a JSON column, so the role test is a LIKE on the serialised array -
     * the same approach UserRepository::findAllExceptAnonymous() already uses. The
     * promotional-code exclusion keeps the delete off a foreign key: a promo code is
     * never issued to an anonymous user in practice, but a row that has one is data,
     * not litter.
     *
     * @return list<int>
     */
    private function findPurgeableIds(\DateTimeImmutable $cutoff, bool $includeUndated): array
    {
        $dsl = 'SELECT u.id FROM ' . User::class . ' u '
            . 'WHERE u.roles LIKE :role '
            . 'AND NOT EXISTS (SELECT pc.id FROM ' . PromotionalCode::class . ' pc WHERE pc.user = u) '
            . 'AND (' . ($includeUndated ? 'u.createdAt IS NULL OR ' : '') . 'u.createdAt < :cutoff)';

        $rows = $this->entityManager->createQuery($dsl)
            ->setParameter('role', '%"ROLE_ANONYMOUS"%')
            ->setParameter('cutoff', $cutoff)
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
