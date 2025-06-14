<?php

namespace App\Command;

use App\Entity\EstateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-estate-types',
    description: 'Seeds the estate_types table with data from JSON',
)]
class SeedEstateTypeCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const ESTATE_TYPES_DATA = [
        [
            "id" => 1,
            "parent" => null,
            "code" => "31",
            "name" => "АПАРТАМЕНТ",
            "position" => 1
        ],
        [
            "id" => 2,
            "parent" => 1,
            "code" => "3100",
            "name" => "АПАРТАМЕНТ",
            "position" => 1
        ],
        [
            "id" => 3,
            "parent" => 1,
            "code" => "3101",
            "name" => "АПАРТАМЕНТ И ГАРАЖ",
            "position" => 2
        ],
        [
            "id" => 4,
            "parent" => null,
            "code" => "32",
            "name" => "КЪЩА",
            "position" => 2
        ],
        [
            "id" => 5,
            "parent" => 4,
            "code" => "3200",
            "name" => "КЪЩА (БЕЗ ДОП. ПОСТРОЙКИ)",
            "position" => 1
        ],
        [
            "id" => 6,
            "parent" => 4,
            "code" => "3201",
            "name" => "КЪЩА И ГАРАЖ",
            "position" => 2
        ],
        [
            "id" => 7,
            "parent" => 4,
            "code" => "3221",
            "name" => "КЪЩА, ОГРАДА И ПОРТА",
            "position" => 3
        ],
        [
            "id" => 8,
            "parent" => 4,
            "code" => "3242",
            "name" => "КЪЩА, ОГРАДА, ПОРТА И ГАРАЖ",
            "position" => 4
        ],
        [
            "id" => 9,
            "parent" => 4,
            "code" => "3247",
            "name" => "КЪЩА И ДОПЪЛНИТЕЛНИ ПОСТРОЙКИ",
            "position" => 5
        ],
        [
            "id" => 10,
            "parent" => 4,
            "code" => "3248",
            "name" => "КЪЩА, ДОПЪЛНИТЕЛНИ ПОСТРОЙКИ И ГАРАЖ",
            "position" => 6
        ],
        [
            "id" => 11,
            "parent" => 4,
            "code" => "3249",
            "name" => "КЪЩА, ДОПЪЛНИТЕЛНИ ПОСТРОЙКИ, ОГРАДА, ПОРТА, ГАРАЖ",
            "position" => 7
        ],
        [
            "id" => 12,
            "parent" => 4,
            "code" => "3250",
            "name" => "КЪЩА, ДОПЪЛНИТЕЛНИ ПОСТРОЙКИ, ОГРАДА, ПОРТА, ГАРАЖ И БАСЕЙН (БЕЗ ИНСТАЛАЦИИ И ОБОРУДВАНЕ)",
            "position" => 8
        ],
        [
            "id" => 13,
            "parent" => null,
            "code" => "33",
            "name" => "ЕТАЖ ОТ КЪЩА",
            "position" => 3
        ],
        [
            "id" => 15,
            "parent" => 13,
            "code" => "3300",
            "name" => "ЕТАЖ ОТ КЪЩА (БЕЗ ДОП. ПОСТРОЙКИ)",
            "position" => 1
        ],
        [
            "id" => 16,
            "parent" => 13,
            "code" => "3301",
            "name" => "ЕТАЖ ОТ КЪЩА И ГАРАЖ",
            "position" => 1
        ],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the estate_types table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding estate_types table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::ESTATE_TYPES_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each estate type
            foreach (self::ESTATE_TYPES_DATA as $estateTypeData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO estate_types (id, parent, code, name, position) VALUES (:id, :parent, :code, :name, :position)';
                $params = [
                    'id' => $estateTypeData['id'],
                    'parent' => $estateTypeData['parent'],
                    'code' => $estateTypeData['code'],
                    'name' => $estateTypeData['name'],
                    'position' => $estateTypeData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Estate types have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the estate_types table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from estate_types table');

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Handle database-specific operations for foreign keys
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // For SQLite, we need to disable foreign key checks temporarily
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        } else if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            // For MySQL/MariaDB, disable foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Delete all records
        $connection->executeStatement('DELETE FROM estate_types');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "estate_types"');
        }

        // Re-enable foreign key checks
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        } else if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $io->comment('Existing data cleared');
    }
}
