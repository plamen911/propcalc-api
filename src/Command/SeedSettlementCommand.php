<?php

namespace App\Command;

use App\Entity\Settlement;
use App\Entity\EarthquakeZone;
use App\Repository\EarthquakeZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-settlements',
    description: 'Seeds the settlements table with data from the Excel file',
)]
class SeedSettlementCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private EarthquakeZoneRepository $earthquakeZoneRepository;

    public function __construct(EntityManagerInterface $entityManager, EarthquakeZoneRepository $earthquakeZoneRepository)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->earthquakeZoneRepository = $earthquakeZoneRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the settlements table with data from the Excel file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding settlements table');

        // Path to the Excel file
        $excelFilePath = __DIR__ . '/../../specs/earthquake_zones.xlsx';

        if (!file_exists($excelFilePath)) {
            $io->error('Excel file not found at: ' . $excelFilePath);
            return Command::FAILURE;
        }

        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($excelFilePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Get all rows from the worksheet
            $rows = $worksheet->toArray();

            // Skip the header row
            array_shift($rows);

            $io->progressStart(count($rows));

            // Process each row
            foreach ($rows as $row) {
                // Check if the row has data
                if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
                    continue;
                }

                // Map the columns to Settlement properties
                $postCode = $row[0];
                $name = $row[1];
                $earthquakeZoneId = (int) $row[2];

                // Find the corresponding EarthquakeZone entity
                $earthquakeZone = $this->earthquakeZoneRepository->find($earthquakeZoneId);

                if (!$earthquakeZone) {
                    $io->warning("EarthquakeZone with ID $earthquakeZoneId not found. Skipping settlements: $name, $postCode");
                    continue;
                }

                // Create a new Settlement entity
                $settlement = new Settlement();
                $settlement->setPostCode($postCode);
                $settlement->setName($name);
                $settlement->setEarthquakeZone($earthquakeZone);

                // Persist the entity
                $this->entityManager->persist($settlement);

                $io->progressAdvance();
            }

            // Flush all changes to the database
            $this->entityManager->flush();

            $io->progressFinish();
            $io->success('Settlements have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
