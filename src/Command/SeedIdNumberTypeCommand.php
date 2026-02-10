<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-id-number-types',
    description: 'Seeds the id_number_type table with data',
)]
class SeedIdNumberTypeCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const ID_NUMBER_TYPE_DATA = [
        [
            "id" => 1,
            "name" => "ЕГН",
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "ЛНЧ",
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Паспорт №",
            "position" => 3
        ],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the id_number_type table with data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding id_number_type table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::ID_NUMBER_TYPE_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each id_number_type entry
            foreach (self::ID_NUMBER_TYPE_DATA as $idNumberTypeData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO id_number_types (id, name, position) VALUES (:id, :name, :position)';
                $params = [
                    'id' => $idNumberTypeData['id'],
                    'name' => $idNumberTypeData['name'],
                    'position' => $idNumberTypeData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('ID number type options have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the id_number_types table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from id_number_types table');

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
        $connection->executeStatement('DELETE FROM id_number_types');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "id_number_types"');
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
