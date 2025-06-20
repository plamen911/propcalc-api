<?php

namespace App\Command;

use App\Entity\InsuranceClause;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-insurance-clauses',
    description: 'Seeds the insurance_clauses table with data from JSON',
)]
class SeedInsuranceClauseCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const INSURANCE_CLAUSES_DATA = [
        [
            "id" => 1,
            "name" => "Пожар и щети - Недвижимо имущество",
            "tariff_number" => 0.11,
            "position" => 1,
            "description" => "Тази клауза покрива щети, причинени от пожар, мълния, експлозия, имплозия, сблъсък или падане на пилотирано летателно тяло, негови части или товар. Включва и разходи за спасяване на застрахованото имущество, ограничаване на вредите и разчистване на останки."
        ],
        [
            "id" => 2,
            "name" => "Пожари и щети - Движимо имущество",
            "tariff_number" => 0.13,
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Пожари и щети на имущество - Соларни инсталации до 15000 лв.",
            "tariff_number" => 1.2,
            "position" => 3
        ],
        [
            "id" => 4,
            "name" => "Наводнение: За обеки до 500 метра от воден басейн",
            "tariff_number" => 0.03,
            "position" => 4
        ],
        [
            "id" => 5,
            "name" => "Наводнение: За обеки над 500 метра от воден басейн",
            "tariff_number" => 0.007,
            "position" => 5
        ],
        [
            "id" => 6,
            "name" => "Земетресение",
            "tariff_number" => 0.0,
            "position" => 6
        ],
        [
            "id" => 7,
            "name" => "Кражба чрез взлом, кражба с техническо средство и грабеж",
            "tariff_number" => 0.52,
            "position" => 7
        ],
        [
            "id" => 8,
            "name" => "Гражданска отговорност към трети лица",
            "tariff_number" => 0.14,
            "position" => 8
        ],
        [
            "id" => 9,
            "name" => "Наем за алтернативно настаняване",
            "tariff_number" => 0.15,
            "position" => 9
        ],
        [
            "id" => 10,
            "name" => "Злополука на член от семейството/домакинството",
            "tariff_number" => 0.14,
            "position" => 10
        ],
        [
            "id" => 11,
            "name" => "Загуба на доход от наем",
            "tariff_number" => 0.07,
            "position" => 11
        ],
        [
            "id" => 12,
            "name" => "Късо съединение и токов удар на електрически инсталации и/или уреди",
            "tariff_number" => 0.07,
            "position" => 12
        ],
        [
            "id" => 13,
            "name" => "Щети вследствие на опит за кражба чрез взлом или грабеж",
            "tariff_number" => 0.007,
            "position" => 13
        ],
        [
            "id" => 14,
            "name" => "Разходи за отключване на брава и издаване на документи",
            "tariff_number" => 0.0,
            "position" => 14
        ],
        [
            "id" => 15,
            "name" => "Гражданска отговорност за вреди, причинени от домашни любимци и злополука на домашен любимец",
            "tariff_number" => 0.0,
            "position" => 15
        ],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the insurance_clauses table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding insurance_clauses table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::INSURANCE_CLAUSES_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each insurance clause
            foreach (self::INSURANCE_CLAUSES_DATA as $insuranceClauseData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO insurance_clauses (id, name, tariff_number, has_tariff_number, tariff_amount, allow_custom_amount, position, description, active) VALUES (:id, :name, :tariff_number, :has_tariff_number, :tariff_amount, :allow_custom_amount, :position, :description, :active)';
                $params = [
                    'id' => $insuranceClauseData['id'],
                    'name' => $insuranceClauseData['name'],
                    'tariff_number' => $insuranceClauseData['tariff_number'],
                    'has_tariff_number' => $insuranceClauseData['id'] == 14 || $insuranceClauseData['id'] == 15 ? 0 : 1,
                    'tariff_amount' => $insuranceClauseData['id'] == 14 || $insuranceClauseData['id'] == 15 ? 2.0 : 0.0,
                    'allow_custom_amount' => in_array($insuranceClauseData['id'], [4, 5, 6, 14, 15]) ? 0 : 1,
                    'position' => $insuranceClauseData['position'],
                    'description' => $insuranceClauseData['description'] ?? null,
                    'active' => true,
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Insurance clauses have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the insurance_clauses table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from insurance_clauses table');

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
        $connection->executeStatement('DELETE FROM insurance_clauses');

        // Reset the auto-increment counter for SQLite
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "insurance_clauses"');
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
