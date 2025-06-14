<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-all',
    description: 'Seeds all database tables with initial data',
)]
class SeedAllCommand extends Command
{
    /**
     * List of seed commands to run in order
     * Order is important to respect foreign key constraints
     */
    private const SEED_COMMANDS = [
        'app:seed-earthquake-zones',
        'app:seed-app-configs',
        'app:seed-estate-types',
        'app:seed-settlements',
        'app:seed-water-distances',
        'app:seed-insurance-clauses',
        'app:seed-tariff-presets',
        'app:seed-tariff-preset-clauses',
        'app:seed-person-roles',
        'app:seed-id-number-types',
        'app:seed-property-checklists',
        'app:seed-nationalities',
    ];

    protected function configure(): void
    {
        $this->setDescription('Seeds all database tables with initial data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding all database tables');

        $application = $this->getApplication();
        if (!$application) {
            $io->error('Could not get application instance');
            return Command::FAILURE;
        }

        $io->progressStart(count(self::SEED_COMMANDS));

        foreach (self::SEED_COMMANDS as $commandName) {
            $command = $application->find($commandName);
            $commandInput = new ArrayInput([]);

            try {
                $io->section("Running $commandName");
                $returnCode = $command->run($commandInput, $output);

                if ($returnCode !== Command::SUCCESS) {
                    $io->error("Command $commandName failed with return code $returnCode");
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $io->error("Error running $commandName: " . $e->getMessage());
                return Command::FAILURE;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('All database tables have been successfully seeded!');

        return Command::SUCCESS;
    }
}
