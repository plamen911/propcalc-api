<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SeedAllCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Replaces the nine mock-based seeder unit tests.
 *
 * Those asserted that a mocked EntityManager received N persist() calls, which mostly
 * proved the mock worked. These assert the rows the seeders actually produce in the
 * test database - in particular the reference ids the pricing logic depends on, which
 * is what a regression here would really break.
 *
 * The database is seeded once in tests/bootstrap.php; these tests read it back. Where a
 * seeder is re-run, dama/doctrine-test-bundle rolls the writes back afterwards.
 */
final class SeedersTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
    }

    /** @return iterable<string, array{string, int}> */
    public static function expectedRowCounts(): iterable
    {
        yield 'earthquake zones' => ['earthquake_zones', 4];
        yield 'app configs' => ['app_configs', 12];
        yield 'estate types' => ['estate_types', 15];
        yield 'water distances' => ['water_distances', 2];
        yield 'insurance clauses' => ['insurance_clauses', 15];
        yield 'tariff presets' => ['tariff_presets', 4];
        yield 'tariff preset clauses' => ['tariff_preset_clauses', 60];
        yield 'person roles' => ['person_roles', 3];
        yield 'id number types' => ['id_number_types', 3];
        yield 'property checklists' => ['property_checklists', 3];
        yield 'nationalities' => ['nationalities', 197];
    }

    #[DataProvider('expectedRowCounts')]
    #[Test]
    public function seedersProduceTheExpectedNumberOfRows(string $table, int $expected): void
    {
        self::assertSame(
            $expected,
            (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table))
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function pricingAppConfigs(): iterable
    {
        yield 'CURRENCY' => ['CURRENCY', '€'];
        yield 'DISCOUNT_PERCENTS' => ['DISCOUNT_PERCENTS', '40'];
        yield 'TAX_PERCENTS' => ['TAX_PERCENTS', '2'];
        yield 'EARTHQUAKE_ID' => ['EARTHQUAKE_ID', '6'];
        yield 'FLOOD_LT_500_M_ID' => ['FLOOD_LT_500_M_ID', '4'];
        yield 'FLOOD_GT_500_M_ID' => ['FLOOD_GT_500_M_ID', '5'];
        yield 'THEFT_DAMAGE_CLAUSE_ID' => ['THEFT_DAMAGE_CLAUSE_ID', '13'];
    }

    #[DataProvider('pricingAppConfigs')]
    #[Test]
    public function pricingConfigsAreSeededWithTheValuesTheServiceExpects(string $name, string $value): void
    {
        self::assertSame(
            $value,
            $this->connection->fetchOne('SELECT value FROM app_configs WHERE name = ?', [$name])
        );
    }

    #[Test]
    public function theClauseIdsNamedByConfigActuallyExist(): void
    {
        foreach (['EARTHQUAKE_ID', 'FLOOD_LT_500_M_ID', 'FLOOD_GT_500_M_ID', 'THEFT_DAMAGE_CLAUSE_ID'] as $name) {
            $clauseId = (int) $this->connection->fetchOne('SELECT value FROM app_configs WHERE name = ?', [$name]);

            self::assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT COUNT(*) FROM insurance_clauses WHERE id = ?', [$clauseId]),
                sprintf('%s points at clause %d, which does not exist.', $name, $clauseId)
            );
        }
    }

    #[Test]
    public function earthquakeZonesCarryTheirTariffNumbers(): void
    {
        $zones = $this->connection->fetchAllKeyValue('SELECT id, tariff_number FROM earthquake_zones ORDER BY id');

        self::assertSame(
            [0.015, 0.025, 0.035, 0.048],
            array_map('floatval', array_values($zones))
        );
    }

    #[Test]
    public function onlyClausesFourteenAndFifteenAreFlatFees(): void
    {
        $flat = $this->connection->fetchFirstColumn(
            'SELECT id FROM insurance_clauses WHERE has_tariff_number = 0 ORDER BY id'
        );

        self::assertSame([14, 15], array_map('intval', $flat));

        $amounts = $this->connection->fetchAllKeyValue(
            'SELECT id, tariff_amount FROM insurance_clauses WHERE id IN (14, 15)'
        );

        foreach ($amounts as $amount) {
            self::assertSame(2.0, (float) $amount, 'The flat clauses charge a fixed 2.00.');
        }
    }

    #[Test]
    public function everyPresetGetsAllFifteenClauses(): void
    {
        $perPreset = $this->connection->fetchAllKeyValue(
            'SELECT tariff_preset_id, COUNT(*) FROM tariff_preset_clauses GROUP BY tariff_preset_id ORDER BY tariff_preset_id'
        );

        self::assertCount(4, $perPreset);
        foreach ($perPreset as $count) {
            self::assertSame(15, (int) $count);
        }
    }

    #[Test]
    public function theBuildingSumInsuredRisesWithEachPreset(): void
    {
        $amounts = $this->connection->fetchAllKeyValue(
            'SELECT tariff_preset_id, tariff_amount FROM tariff_preset_clauses WHERE insurance_clause_id = 1 ORDER BY tariff_preset_id'
        );

        self::assertSame(
            [120000.0, 150000.0, 320000.0, 400000.0],
            array_map('floatval', array_values($amounts))
        );
    }

    #[Test]
    public function waterDistanceIdOneMeansWithinFiveHundredMetres(): void
    {
        // TariffPresetService branches on `$distanceToWaterId === 1`, so this mapping is
        // load-bearing for flood clause filtering.
        self::assertSame(
            'Обекти до 500 метра',
            $this->connection->fetchOne('SELECT name FROM water_distances WHERE id = 1')
        );
    }

    #[Test]
    public function seedAllListsTheTwelveSeedersInForeignKeyOrder(): void
    {
        $order = (new \ReflectionClass(SeedAllCommand::class))->getConstant('SEED_COMMANDS');

        self::assertSame([
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
        ], $order);
    }

    #[Test]
    public function everySeederNamedBySeedAllIsRegistered(): void
    {
        $application = new Application(self::$kernel);
        $order = (new \ReflectionClass(SeedAllCommand::class))->getConstant('SEED_COMMANDS');

        foreach ($order as $name) {
            self::assertInstanceOf(Command::class, $application->find($name));
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function rerunnableSeeders(): iterable
    {
        yield 'app configs' => ['app:seed-app-configs', 'app_configs'];
        yield 'insurance clauses' => ['app:seed-insurance-clauses', 'insurance_clauses'];
        yield 'water distances' => ['app:seed-water-distances', 'water_distances'];
        yield 'person roles' => ['app:seed-person-roles', 'person_roles'];
        yield 'id number types' => ['app:seed-id-number-types', 'id_number_types'];
        yield 'property checklists' => ['app:seed-property-checklists', 'property_checklists'];
    }

    #[DataProvider('rerunnableSeeders')]
    #[Test]
    public function reRunningASeederDoesNotDuplicateRows(string $commandName, string $table): void
    {
        $before = (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));

        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find($commandName));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            $before,
            (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table)),
            sprintf('%s should clear %s before re-seeding.', $commandName, $table)
        );
    }
}
