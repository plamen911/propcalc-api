<?php

namespace App\Command;

use App\Entity\TariffPreset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-tariff-presets',
    description: 'Seeds the tariff_presets table with data from JSON',
)]
class SeedTariffPresetCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const TARIFF_PRESETS_DATA = [
        [
            "id" => 1,
            "name" => "Пакет 1",
            "active" => true,
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "Пакет 2",
            "active" => true,
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Пакет 3",
            "active" => true,
            "position" => 3
        ],
        [
            "id" => 4,
            "name" => "Пакет 4",
            "active" => true,
            "position" => 4
        ],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the tariff_presets table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding tariff_presets table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::TARIFF_PRESETS_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each tariff preset
            foreach (self::TARIFF_PRESETS_DATA as $tariffPresetData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO tariff_presets (id, name, active, position) VALUES (:id, :name, :active, :position)';
                $params = [
                    'id' => $tariffPresetData['id'],
                    'name' => $tariffPresetData['name'],
                    'active' => $tariffPresetData['active'] ? 1 : 0, // Convert boolean to integer for SQLite
                    'position' => $tariffPresetData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Tariff presets have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the tariff_presets table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from tariff_presets table');

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
        $connection->executeStatement('DELETE FROM tariff_presets');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = :table_name', ['table_name' => 'tariff_presets']);
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
