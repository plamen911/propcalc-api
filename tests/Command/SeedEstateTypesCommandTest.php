<?php

namespace App\Tests\Command;

use App\Command\SeedEstateTypeCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedEstateTypesCommandTest extends TestCase
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
        $this->command = new SeedEstateTypeCommand($this->entityManager);

        // Create an application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($application->find('app:seed-estate-types'));
    }

    public function testExecute(): void
    {
        // The Connection should expect executeStatement() to be called for clearing data
        $this->connection->expects($this->exactly(4))
            ->method('executeStatement')
            ->withConsecutive(
                ['PRAGMA foreign_keys = OFF'],
                ['DELETE FROM estate_types'],
                ['DELETE FROM sqlite_sequence WHERE name = "estate_types"'],
                ['PRAGMA foreign_keys = ON']
            );

        // The Connection should expect executeStatement() to be called for each estate type
        $this->connection->expects($this->exactly(15))
            ->method('executeStatement')
            ->with(
                $this->equalTo('INSERT INTO estate_types (id, parent, code, name, position) VALUES (:id, :parent, :code, :name, :position)'),
                $this->callback(function ($params) {
                    return isset($params['id']) && isset($params['code']) && isset($params['name']) && isset($params['position']);
                })
            );

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Estate types have been successfully seeded!', $output);
    }
}
