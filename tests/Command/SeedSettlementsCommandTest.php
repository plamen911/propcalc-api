<?php

namespace App\Tests\Command;

use App\Command\SeedSettlementCommand;
use App\Entity\Settlement;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedSettlementsCommandTest extends TestCase
{
    private $entityManager;
    private $command;
    private $commandTester;

    protected function setUp(): void
    {
        // Create a mock of the EntityManager
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Create the command
        $this->command = new SeedSettlementCommand($this->entityManager);

        // Create an application and add the command
        $application = new Application();
        $application->add($this->command);

        // Create a command tester
        $this->commandTester = new CommandTester($application->find('app:seed-settlements'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testExecute(): void
    {
        // Mock the IOFactory to return a mock Spreadsheet
        $this->mockPhpSpreadsheet();

        // The EntityManager should expect persist() to be called for each settlement
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(Settlement::class));

        // The EntityManager should expect flush() to be called once
        $this->entityManager->expects($this->once())
            ->method('flush');

        // Execute the command
        $this->commandTester->execute([]);

        // Assert that the command was successful
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Assert that the output contains the success message
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Settlements have been successfully seeded!', $output);
    }

    private function mockPhpSpreadsheet(): void
    {
        // Create a mock of the Worksheet
        $worksheet = $this->createMock(Worksheet::class);

        // The worksheet should return an array of rows when toArray() is called
        $worksheet->expects($this->once())
            ->method('toArray')
            ->willReturn([
                ['Post Code', 'Name', 'Earthquake Zone'], // Header row (will be skipped)
                ['1000', 'Sofia', 2],
                ['2000', 'Plovdiv', 3],
                ['3000', 'Varna', 1],
            ]);

        // Create a mock of the Spreadsheet
        $spreadsheet = $this->createMock(Spreadsheet::class);

        // The spreadsheet should return the worksheet when getActiveSheet() is called
        $spreadsheet->expects($this->once())
            ->method('getActiveSheet')
            ->willReturn($worksheet);

        // Mock the IOFactory::load() method to return the mock spreadsheet
        $mockBuilder = $this->getMockBuilder('PhpOffice\PhpSpreadsheet\IOFactory');
        $mockBuilder->setMethods(['load']);
        $mockIOFactory = $mockBuilder->getMock();
        $mockIOFactory->expects($this->once())
            ->method('load')
            ->willReturn($spreadsheet);

        // Replace the IOFactory class with our mock
        class_alias(get_class($mockIOFactory), 'PhpOffice\PhpSpreadsheet\IOFactory');
    }
}
