<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-earthquake-zones',
    description: 'Seeds the earthquake_zones table with data from JSON',
)]
class SeedEarthquakeZoneCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const EARTHQUAKE_ZONES_DATA = [
        [
            "id" => 1,
            "name" => "Зона 1",
            "tariff_number" => 0.015,
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "Зона 2",
            "tariff_number" => 0.025,
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Зона 3",
            "tariff_number" => 0.035,
            "position" => 3
        ],
        [
            "id" => 4,
            "name" => "Зона 4",
            "tariff_number" => 0.048,
            "position" => 4
        ]
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the earthquake_zones table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding earthquake_zones table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::EARTHQUAKE_ZONES_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each earthquake zone
            foreach (self::EARTHQUAKE_ZONES_DATA as $earthquakeZoneData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO earthquake_zones (id, name, tariff_number, position) VALUES (:id, :name, :tariff_number, :position)';
                $params = [
                    'id' => $earthquakeZoneData['id'],
                    'name' => $earthquakeZoneData['name'],
                    'tariff_number' => $earthquakeZoneData['tariff_number'],
                    'position' => $earthquakeZoneData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Earthquake zones have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the earthquake_zones table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from earthquake_zones table');

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
        $connection->executeStatement('DELETE FROM earthquake_zones');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "earthquake_zones"');
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
