<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SeedAppConfigCommand;
use App\Entity\AppConfig;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedAppConfigsCommandTest extends TestCase
{
    private $entityManager;
    private $command;
    private $commandTester;
    private $query;

    protected function setUp(): void
    {
        // Create a mock of the Query
        $this->query = $this->createMock(Query::class);

        // Create a mock of the EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // The EntityManager should return the Query when createQuery() is called
        $this->entityManager->expects($this->once())
            ->method('createQuery')
            ->with('DELETE FROM App\Entity\AppConfig')
            ->willReturn($this->query);

        // The Query should return the number of deleted records when execute() is called
        $this->query->expects($this->once())
            ->method('execute')
            ->willReturn(0);

        // Create the command
        $this->command = new SeedAppConfigCommand($this->entityManager);

        // Create an application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($application->find('app:seed-app-configs'));
    }

    public function testExecute(): void
    {
        // The EntityManager should expect persist() to be called for each app config
        $this->entityManager->expects($this->exactly(6))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof AppConfig
                    && $entity->getName() !== null
                    && $entity->getValue() !== null;
            }));

        // The EntityManager should expect flush() to be called once
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('App configs have been successfully seeded!', $output);
    }
}
