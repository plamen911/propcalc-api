<?php

namespace App\Tests\Command;

use App\Command\SeedAllCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SeedAllCommandTest extends TestCase
{
    private $application;
    private $command;
    private $commandTester;

    protected function setUp(): void
    {
        // Create a mock of the Application
        $this->application = $this->createMock(Application::class);

        // Create the command
        $this->command = new SeedAllCommand();
        $this->command->setApplication($this->application);

        // Create a command tester
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecute(): void
    {
        // Create mock commands for each seed command
        $mockCommands = [];
        $seedCommands = [
            'app:seed-insurance-policy-configs',
            'app:seed-estate-types',
            'app:seed-settlements',
            'app:seed-water-distance',
            'app:seed-insurance-clauses',
            'app:seed-tariff-presets',
            'app:seed-tariff-preset-clauses',
        ];

        foreach ($seedCommands as $commandName) {
            $mockCommand = $this->createMock(Command::class);
            $mockCommand->expects($this->once())
                ->method('run')
                ->willReturn(Command::SUCCESS);
            $mockCommands[$commandName] = $mockCommand;
        }

        // The Application should expect find() to be called for each seed command
        $this->application->expects($this->exactly(count($seedCommands)))
            ->method('find')
            ->willReturnCallback(function ($commandName) use ($mockCommands) {
                return $mockCommands[$commandName] ?? null;
            });

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('All database tables have been successfully seeded!', $output);
    }

    public function testExecuteWithFailingCommand(): void
    {
        // Create mock commands with one failing command
        $mockCommands = [];
        $seedCommands = [
            'app:seed-insurance-policy-configs',
            'app:seed-estate-types',
            'app:seed-settlements',
            'app:seed-water-distance',
            'app:seed-insurance-clauses',
            'app:seed-tariff-presets',
            'app:seed-tariff-preset-clauses',
        ];

        foreach ($seedCommands as $index => $commandName) {
            $mockCommand = $this->createMock(Command::class);

            // Make the third command fail
            if ($index === 2) {
                $mockCommand->expects($this->once())
                    ->method('run')
                    ->willReturn(Command::FAILURE);
            } else {
                $mockCommand->expects($this->any())
                    ->method('run')
                    ->willReturn(Command::SUCCESS);
            }

            $mockCommands[$commandName] = $mockCommand;
        }

        // The Application should expect find() to be called for each seed command until failure
        $this->application->expects($this->any())
            ->method('find')
            ->willReturnCallback(function ($commandName) use ($mockCommands) {
                return $mockCommands[$commandName] ?? null;
            });

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command failed
        $this->assertEquals(1, $this->commandTester->getStatusCode());

        // Assert that the output contains the error message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('failed with return code', $output);
    }
}
