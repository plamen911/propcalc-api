<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-property-checklists',
    description: 'Seeds the property_checklist table with data',
)]
class SeedPropertyChecklistCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const PROPERTY_CHECKLIST_DATA = [
        [
            "id" => 1,
            "name" => "Обектът обитава ли се целогодишно?",
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "Сградата въведена ли е редовно в експлоатация със законоустановен документ?",
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Сградата масивна конструкция ли е?",
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
        $this->setDescription('Seeds the property_checklists table with data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding property_checklists table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::PROPERTY_CHECKLIST_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each property_checklists entry
            foreach (self::PROPERTY_CHECKLIST_DATA as $propertyChecklistData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO property_checklists (id, name, position) VALUES (:id, :name, :position)';
                $params = [
                    'id' => $propertyChecklistData['id'],
                    'name' => $propertyChecklistData['name'],
                    'position' => $propertyChecklistData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Property checklist items have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the property_checklists table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from property_checklists table');

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
        $connection->executeStatement('DELETE FROM property_checklists');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "property_checklists"');
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
