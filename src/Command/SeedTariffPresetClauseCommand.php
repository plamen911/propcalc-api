<?php

namespace App\Command;

use App\Entity\TariffPresetClause;
use App\Entity\TariffPreset;
use App\Entity\InsuranceClause;
use App\Repository\TariffPresetRepository;
use App\Repository\InsuranceClauseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-tariff-preset-clauses',
    description: 'Seeds the tariff_preset_clauses table with data from JSON',
)]
class SeedTariffPresetClauseCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private TariffPresetRepository $tariffPresetRepository;
    private InsuranceClauseRepository $insuranceClauseRepository;

    // JSON data from the issue description
    private const TARIFF_PRESET_CLAUSES_DATA = [
        [
            "id" => 1, "tariff_preset_id" => 1, "insurance_clause_id" => 1, "tariff_amount" => 120000, "position" => 1
        ],
        [
            "id" => 1, "tariff_preset_id" => 2, "insurance_clause_id" => 2, "tariff_amount" => 20000, "position" => 2
        ],
        [
            "id" => 1, "tariff_preset_id" => 3, "insurance_clause_id" => 3, "tariff_amount" => 0, "position" => 3
        ],
        [
            "id" => 1, "tariff_preset_id" => 4, "insurance_clause_id" => 4, "tariff_amount" => 20000, "position" => 4
        ],
        [
            "id" => 1, "tariff_preset_id" => 5, "insurance_clause_id" => 5, "tariff_amount" => 2, "position" => 5
        ],
        [
            "id" => 1, "tariff_preset_id" => 6, "insurance_clause_id" => 6, "tariff_amount" => 140000, "position" => 6
        ],
        [
            "id" => 1, "tariff_preset_id" => 7, "insurance_clause_id" => 7, "tariff_amount" => 0, "position" => 7
        ],
        [
            "id" => 1, "tariff_preset_id" => 8, "insurance_clause_id" => 8, "tariff_amount" => 5000, "position" => 8
        ],
        [
            "id" => 1, "tariff_preset_id" => 9, "insurance_clause_id" => 9, "tariff_amount" => 3000, "position" => 9
        ],
        [
            "id" => 1, "tariff_preset_id" => 10, "insurance_clause_id" => 10, "tariff_amount" => 3000, "position" => 10
        ],
        [
            "id" => 1, "tariff_preset_id" => 11, "insurance_clause_id" => 11, "tariff_amount" => 0, "position" => 11
        ],
        [
            "id" => 1, "tariff_preset_id" => 12, "insurance_clause_id" => 12, "tariff_amount" => 10000, "position" => 12
        ],
        [
            "id" => 1, "tariff_preset_id" => 13, "insurance_clause_id" => 13, "tariff_amount" => 28571, "position" => 13
        ],
        [
            "id" => 1, "tariff_preset_id" => 14, "insurance_clause_id" => 14, "tariff_amount" => 300, "position" => 14
        ],
        [
            "id" => 1, "tariff_preset_id" => 15, "insurance_clause_id" => 15, "tariff_amount" => 500, "position" => 15
        ],
        [
            "id" => 2, "tariff_preset_id" => 1, "insurance_clause_id" => 1, "tariff_amount" => 150000, "position" => 1
        ],
        [
            "id" => 2, "tariff_preset_id" => 2, "insurance_clause_id" => 2, "tariff_amount" => 30000, "position" => 2
        ],
        [
            "id" => 2, "tariff_preset_id" => 3, "insurance_clause_id" => 3, "tariff_amount" => 0, "position" => 3
        ],
        [
            "id" => 2, "tariff_preset_id" => 4, "insurance_clause_id" => 4, "tariff_amount" => 30000, "position" => 4
        ],
        [
            "id" => 2, "tariff_preset_id" => 5, "insurance_clause_id" => 5, "tariff_amount" => 2, "position" => 5
        ],
        [
            "id" => 2, "tariff_preset_id" => 6, "insurance_clause_id" => 6, "tariff_amount" => 180000, "position" => 6
        ],
        [
            "id" => 2, "tariff_preset_id" => 7, "insurance_clause_id" => 7, "tariff_amount" => 0, "position" => 7
        ],
        [
            "id" => 2, "tariff_preset_id" => 8, "insurance_clause_id" => 8, "tariff_amount" => 10000, "position" => 8
        ],
        [
            "id" => 2, "tariff_preset_id" => 9, "insurance_clause_id" => 9, "tariff_amount" => 4000, "position" => 9
        ],
        [
            "id" => 2, "tariff_preset_id" => 10, "insurance_clause_id" => 10, "tariff_amount" => 4000, "position" => 10
        ],
        [
            "id" => 2, "tariff_preset_id" => 11, "insurance_clause_id" => 11, "tariff_amount" => 0, "position" => 11
        ],
        [
            "id" => 2, "tariff_preset_id" => 12, "insurance_clause_id" => 12, "tariff_amount" => 15000, "position" => 12
        ],
        [
            "id" => 2, "tariff_preset_id" => 13, "insurance_clause_id" => 13, "tariff_amount" => 28571, "position" => 13
        ],
        [
            "id" => 2, "tariff_preset_id" => 14, "insurance_clause_id" => 14, "tariff_amount" => 300, "position" => 14
        ],
        [
            "id" => 2, "tariff_preset_id" => 15, "insurance_clause_id" => 15, "tariff_amount" => 500, "position" => 15
        ],
        [
            "id" => 3, "tariff_preset_id" => 1, "insurance_clause_id" => 1, "tariff_amount" => 320000, "position" => 1
        ],
        [
            "id" => 3, "tariff_preset_id" => 2, "insurance_clause_id" => 2, "tariff_amount" => 60000, "position" => 2
        ],
        [
            "id" => 3, "tariff_preset_id" => 3, "insurance_clause_id" => 3, "tariff_amount" => 0, "position" => 3
        ],
        [
            "id" => 3, "tariff_preset_id" => 4, "insurance_clause_id" => 4, "tariff_amount" => 60000, "position" => 4
        ],
        [
            "id" => 3, "tariff_preset_id" => 5, "insurance_clause_id" => 5, "tariff_amount" => 2, "position" => 5
        ],
        [
            "id" => 3, "tariff_preset_id" => 6, "insurance_clause_id" => 6, "tariff_amount" => 380000, "position" => 6
        ],
        [
            "id" => 3, "tariff_preset_id" => 7, "insurance_clause_id" => 7, "tariff_amount" => 0, "position" => 7
        ],
        [
            "id" => 3, "tariff_preset_id" => 8, "insurance_clause_id" => 8, "tariff_amount" => 15000, "position" => 8
        ],
        [
            "id" => 3, "tariff_preset_id" => 9, "insurance_clause_id" => 9, "tariff_amount" => 5000, "position" => 9
        ],
        [
            "id" => 3, "tariff_preset_id" => 10, "insurance_clause_id" => 10, "tariff_amount" => 5000, "position" => 10
        ],
        [
            "id" => 3, "tariff_preset_id" => 11, "insurance_clause_id" => 11, "tariff_amount" => 0, "position" => 11
        ],
        [
            "id" => 3, "tariff_preset_id" => 12, "insurance_clause_id" => 12, "tariff_amount" => 30000, "position" => 12
        ],
        [
            "id" => 3, "tariff_preset_id" => 13, "insurance_clause_id" => 13, "tariff_amount" => 28571, "position" => 13
        ],
        [
            "id" => 3, "tariff_preset_id" => 14, "insurance_clause_id" => 14, "tariff_amount" => 300, "position" => 14
        ],
        [
            "id" => 3, "tariff_preset_id" => 15, "insurance_clause_id" => 15, "tariff_amount" => 500, "position" => 15
        ],


        [
            "id" => 4, "tariff_preset_id" => 1, "insurance_clause_id" => 1, "tariff_amount" => 400000, "position" => 1
        ],
        [
            "id" => 4, "tariff_preset_id" => 2, "insurance_clause_id" => 2, "tariff_amount" => 70000, "position" => 2
        ],
        [
            "id" => 4, "tariff_preset_id" => 3, "insurance_clause_id" => 3, "tariff_amount" => 0, "position" => 3
        ],
        [
            "id" => 4, "tariff_preset_id" => 4, "insurance_clause_id" => 4, "tariff_amount" => 70000, "position" => 4
        ],
        [
            "id" => 4, "tariff_preset_id" => 5, "insurance_clause_id" => 5, "tariff_amount" => 2, "position" => 5
        ],
        [
            "id" => 4, "tariff_preset_id" => 6, "insurance_clause_id" => 6, "tariff_amount" => 470000, "position" => 6
        ],
        [
            "id" => 4, "tariff_preset_id" => 7, "insurance_clause_id" => 7, "tariff_amount" => 0, "position" => 7
        ],
        [
            "id" => 4, "tariff_preset_id" => 8, "insurance_clause_id" => 8, "tariff_amount" => 15000, "position" => 8
        ],
        [
            "id" => 4, "tariff_preset_id" => 9, "insurance_clause_id" => 9, "tariff_amount" => 5000, "position" => 9
        ],
        [
            "id" => 4, "tariff_preset_id" => 10, "insurance_clause_id" => 10, "tariff_amount" => 5000, "position" => 10
        ],
        [
            "id" => 4, "tariff_preset_id" => 11, "insurance_clause_id" => 11, "tariff_amount" => 0, "position" => 11
        ],
        [
            "id" => 4, "tariff_preset_id" => 12, "insurance_clause_id" => 12, "tariff_amount" => 30000, "position" => 12
        ],
        [
            "id" => 4, "tariff_preset_id" => 13, "insurance_clause_id" => 13, "tariff_amount" => 28571, "position" => 13
        ],
        [
            "id" => 4, "tariff_preset_id" => 14, "insurance_clause_id" => 14, "tariff_amount" => 300, "position" => 14
        ],
        [
            "id" => 4, "tariff_preset_id" => 15, "insurance_clause_id" => 15, "tariff_amount" => 500, "position" => 15
        ],
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        TariffPresetRepository $tariffPresetRepository,
        InsuranceClauseRepository $insuranceClauseRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->tariffPresetRepository = $tariffPresetRepository;
        $this->insuranceClauseRepository = $insuranceClauseRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the tariff_preset_clauses table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding tariff_preset_clauses table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::TARIFF_PRESET_CLAUSES_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each tariff preset clause
            $counter = 1; // Counter for generating unique IDs
            foreach (self::TARIFF_PRESET_CLAUSES_DATA as $tariffPresetClauseData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO tariff_preset_clauses (id, tariff_preset_id, insurance_clause_id, tariff_amount, position) VALUES (:id, :tariff_preset_id, :insurance_clause_id, :tariff_amount, :position)';
                $params = [
                    'id' => $counter++, // Use counter for unique ID
                    'tariff_preset_id' => $tariffPresetClauseData['id'], // Map id to tariff_preset_id
                    'insurance_clause_id' => $tariffPresetClauseData['tariff_preset_id'], // Map tariff_preset_id to insurance_clause_id
                    'tariff_amount' => $tariffPresetClauseData['tariff_amount'],
                    'position' => $tariffPresetClauseData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Tariff preset clauses have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the tariff_preset_clauses table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from tariff_preset_clauses table');

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
        $connection->executeStatement('DELETE FROM tariff_preset_clauses');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = :table_name', ['table_name' => 'tariff_preset_clauses']);
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
