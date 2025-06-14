<?php

namespace App\Command;

/**
 * This is a demonstration script that shows how the SeedSettlementsCommand would work
 * if it were run with the PhpSpreadsheet library installed.
 *
 * This script is for documentation purposes only and is not meant to be executed.
 */
class DemoSeedSettlementScript
{
    /**
     * This method simulates what would happen when the SeedSettlementsCommand is executed.
     */
    public static function simulateExecution(): void
    {
        echo "Starting settlement seeder...\n";

        // Simulating loading the Excel file
        echo "Loading Excel file from specs/earthquake_zones.xlsx...\n";

        // Simulating reading the data (this is example data)
        $exampleData = [
            ['Post Code', 'Name', 'Earthquake Zone'], // Header row (will be skipped)
            ['1000', 'Sofia', 2],
            ['2000', 'Plovdiv', 3],
            ['3000', 'Varna', 1],
            ['4000', 'Burgas', 1],
            ['5000', 'Veliko Tarnovo', 2],
        ];

        echo "Excel file loaded successfully.\n";
        echo "Found " . (count($exampleData) - 1) . " settlements (excluding header row).\n";

        // Simulating skipping the header row
        echo "Skipping header row...\n";
        array_shift($exampleData);

        // Simulating processing each row
        echo "Processing settlements:\n";
        foreach ($exampleData as $index => $row) {
            $postCode = $row[0];
            $name = $row[1];
            $earthquakeZone = $row[2];

            echo "  - Settlement #" . ($index + 1) . ": Post Code = $postCode, Name = $name, Earthquake Zone = $earthquakeZone\n";

            // Simulating creating and persisting a Settlement entity
            echo "    Creating Settlement entity...\n";
            echo "    Persisting Settlement entity...\n";
        }

        // Simulating flushing changes to the database
        echo "Flushing changes to the database...\n";

        echo "Seeding completed successfully!\n";
        echo "Added " . count($exampleData) . " settlements to the database.\n";
    }
}

// If this script were to be run directly, it would output the simulation
// DemoSeedSettlementsScript::simulateExecution();
