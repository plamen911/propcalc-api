<?php

namespace App\Command;

use App\Repository\SettlementRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:remove-duplicate-settlements',
    description: 'Removes duplicate settlements from the database',
)]
class RemoveDuplicateSettlementsCommand extends Command
{
    private SettlementRepository $settlementRepository;

    public function __construct(SettlementRepository $settlementRepository)
    {
        parent::__construct();
        $this->settlementRepository = $settlementRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Removes duplicate settlements from the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Removing duplicate settlements');

        try {
            $io->info('Searching for duplicate settlements...');

            // Call the repository method to remove duplicates
            $removedCount = $this->settlementRepository->removeDuplicates();

            if ($removedCount > 0) {
                $io->success(sprintf('Successfully removed %d duplicate settlements!', $removedCount));
            } else {
                $io->info('No duplicate settlements found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
