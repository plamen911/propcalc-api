<?php

namespace App\Tests\Command;

use App\Command\SeedTariffPresetClauseCommand;
use App\Repository\TariffPresetRepository;
use App\Repository\InsuranceClauseRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedTariffPresetClausesCommandTest extends TestCase
{
    private $entityManager;
    private $connection;
    private $tariffPresetRepository;
    private $insuranceClauseRepository;
    private $command;
    private $commandTester;

    protected function setUp(): void
    {
        // Create a mock of the Connection
        $this->connection = $this->createMock(Connection::class);

        // Create a mock of the EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Create mocks of the repositories
        $this->tariffPresetRepository = $this->createMock(TariffPresetRepository::class);
        $this->insuranceClauseRepository = $this->createMock(InsuranceClauseRepository::class);

        // The EntityManager should return the Connection when getConnection() is called
        $this->entityManager->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);

        // Create the command
        $this->command = new SeedTariffPresetClauseCommand(
            $this->entityManager,
            $this->tariffPresetRepository,
            $this->insuranceClauseRepository
        );

        // Create an application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($application->find('app:seed-tariff-preset-clauses'));
    }

    public function testExecute(): void
    {
        // The Connection should expect executeStatement() to be called for clearing data
        $this->connection->expects($this->exactly(4))
            ->method('executeStatement')
            ->withConsecutive(
                ['PRAGMA foreign_keys = OFF'],
                ['DELETE FROM tariff_preset_clauses'],
                ['DELETE FROM sqlite_sequence WHERE name = :table_name', ['table_name' => 'tariff_preset_clauses']],
                ['PRAGMA foreign_keys = ON']
            );

        // The Connection should expect executeStatement() to be called for each tariff preset clause
        $this->connection->expects($this->exactly(30))
            ->method('executeStatement')
            ->with(
                $this->equalTo('INSERT INTO tariff_preset_clauses (id, tariff_preset_id, insurance_clause_id, tariff_amount, position) VALUES (:id, :tariff_preset_id, :insurance_clause_id, :tariff_amount, :position)'),
                $this->callback(function ($params) {
                    return isset($params['id']) && isset($params['tariff_preset_id']) && isset($params['insurance_clause_id'])
                        && isset($params['tariff_amount']) && isset($params['position']);
                })
            );

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Tariff preset clauses have been successfully seeded!', $output);
    }
}
