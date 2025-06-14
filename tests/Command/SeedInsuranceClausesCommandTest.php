<?php

namespace App\Tests\Command;

use App\Command\SeedInsuranceClauseCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedInsuranceClausesCommandTest extends TestCase
{
    private $entityManager;
    private $connection;
    private $command;
    private $commandTester;

    protected function setUp(): void
    {
        // Create a mock of the Connection
        $this->connection = $this->createMock(Connection::class);

        // Create a mock of the EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // The EntityManager should return the Connection when getConnection() is called
        $this->entityManager->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connection);

        // Create the command
        $this->command = new SeedInsuranceClauseCommand($this->entityManager);

        // Create an application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($application->find('app:seed-insurance-clauses'));
    }

    public function testExecute(): void
    {
        // The Connection should expect executeStatement() to be called for clearing data
        $this->connection->expects($this->exactly(4))
            ->method('executeStatement')
            ->withConsecutive(
                ['PRAGMA foreign_keys = OFF'],
                ['DELETE FROM insurance_clauses'],
                ['DELETE FROM sqlite_sequence WHERE name = "insurance_clauses"'],
                ['PRAGMA foreign_keys = ON']
            );

        // The Connection should expect executeStatement() to be called for each insurance clause
        $this->connection->expects($this->exactly(15))
            ->method('executeStatement')
            ->with(
                $this->equalTo('INSERT INTO insurance_clauses (id, name, tariff_number, has_tariff_number, tariff_amount, position) VALUES (:id, :name, :tariff_number, :has_tariff_number, :tariff_amount, :position)'),
                $this->callback(function ($params) {
                    return isset($params['id']) && isset($params['name']) && isset($params['tariff_number'])
                        && isset($params['has_tariff_number']) && isset($params['tariff_amount']) && isset($params['position']);
                })
            );

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Insurance clauses have been successfully seeded!', $output);
    }
}
