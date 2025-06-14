<?php

namespace App\Tests\Command;

use App\Command\SeedWaterDistanceCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedWaterDistanceCommandTest extends TestCase
{
    private $entityManager;
    private $command;
    private $commandTester;

    protected function setUp(): void
    {
        // Create a mock for the EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Create a mock for the database connection
        $connection = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['executeStatement'])
            ->getMock();

        // Configure the connection mock to expect executeStatement calls
        $connection->expects($this->atLeastOnce())
            ->method('executeStatement');

        // Configure the EntityManager to return the connection mock
        $this->entityManager->expects($this->any())
            ->method('getConnection')
            ->willReturn($connection);

        // Create the command
        $this->command = new SeedWaterDistanceCommand($this->entityManager);

        // Create the application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecute(): void
    {
        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Water distance records have been successfully seeded', $output);
    }
}
