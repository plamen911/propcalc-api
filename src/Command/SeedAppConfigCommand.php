<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AppConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-app-configs',
    description: 'Seeds the app_configs table with data from JSON',
)]
class SeedAppConfigCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data from the issue description
    private const APP_CONFIGS_DATA = [
        [
            "id" => 1,
            "name" => "CURRENCY",
            "value" => "лв.",
            "name_bg" => "Валута",
            "is_editable" => true,
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "DISCOUNT_PERCENTS",
            "value" => "40",
            "name_bg" => "Отстъпка, %",
            "is_editable" => true,
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "EARTHQUAKE_ID",
            "value" => "6",
            "name_bg" => "Клауза земетресение",
            "is_editable" => false,
            "position" => 3
        ],
        [
            "id" => 4,
            "name" => "FLOOD_LT_500_M_ID",
            "value" => "4",
            "name_bg" => "Клауза наводнение до 500 метра от воден басейн",
            "is_editable" => false,
            "position" => 4
        ],
        [
            "id" => 5,
            "name" => "FLOOD_GT_500_M_ID",
            "value" => "5",
            "name_bg" => "Клауза наводнение над 500 метра от воден басейн",
            "is_editable" => false,
            "position" => 5
        ],
        [
            "id" => 6,
            "name" => "TAX_PERCENTS",
            "value" => "2",
            "name_bg" => "Данък върху застрахователната премия, %",
            "is_editable" => true,
            "position" => 6
        ],
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the app_configs table with data from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding app_configs table');

        try {
            // Clear existing data
            $this->clearExistingData($io);

            $io->progressStart(count(self::APP_CONFIGS_DATA));

            // Process each app config
            foreach (self::APP_CONFIGS_DATA as $configData) {
                // Create a new AppConfig entity
                $appConfig = new AppConfig();
                $appConfig->setName($configData['name']);
                $appConfig->setValue($configData['value']);

                // Set nameBg if it exists
                if (isset($configData['name_bg'])) {
                    $appConfig->setNameBg($configData['name_bg']);
                }

                // Set isEditable if it exists
                if (isset($configData['is_editable'])) {
                    $appConfig->setIsEditable($configData['is_editable']);
                }

                // Set position if it exists
                if (isset($configData['position'])) {
                    $appConfig->setPosition($configData['position']);
                }

                // Persist the entity
                $this->entityManager->persist($appConfig);

                $io->progressAdvance();
            }

            // Flush all changes to the database
            $this->entityManager->flush();

            $io->progressFinish();
            $io->success('App configs have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the app_configs table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from app_configs table');

        try {
            // Use DQL to delete all records
            $query = $this->entityManager->createQuery('DELETE FROM App\Entity\AppConfig');
            $numDeleted = $query->execute();

            $io->comment("Deleted $numDeleted existing records");
        } catch (\Exception $e) {
            $io->warning('Could not clear existing data: ' . $e->getMessage());
            // Continue anyway, we'll handle errors later
        }
    }
}
