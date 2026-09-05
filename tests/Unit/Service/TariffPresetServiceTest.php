<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TariffPresetService;
use App\Tests\Support\PricingDoubles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TariffPresetService::class)]
final class TariffPresetServiceTest extends TestCase
{
    use PricingDoubles;

    /** Convenience: the single preset's clause rows from getTariffPresets(). */
    private function clauseRows(array $result): array
    {
        return $result[0]['tariff_preset_clauses'];
    }

    // -----------------------------------------------------------------------
    // has_tariff_number: the two ways a line total can be produced
    // -----------------------------------------------------------------------

    #[Test]
    public function percentageClauseMultipliesThePresetSumInsuredByTheTariffNumber(): void
    {
        $service = $this->buildService([
            ['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 120000.0],
        ]);

        $rows = $this->clauseRows($service->getTariffPresets());

        // 120000 * 0.11 / 100
        self::assertSame('132.00', $rows[0]['line_total']);
        self::assertSame('120000.00', $rows[0]['tariff_amount']);
        self::assertTrue($rows[0]['insurance_clause']['has_tariff_number']);
    }

    #[Test]
    public function flatClauseUsesTheInsuranceClauseAmountAndIgnoresThePresetAmount(): void
    {
        // This is what makes seeded clauses 14 and 15 behave differently. There is no
        // id-14 or id-15 branch anywhere in the service - only has_tariff_number = false.
        $service = $this->buildService([
            ['id' => 15, 'hasTariffNumber' => false, 'clauseFlatAmount' => 2.0, 'presetAmount' => 500.0],
        ]);

        $rows = $this->clauseRows($service->getTariffPresets());

        self::assertSame('2.00', $rows[0]['line_total'], 'The flat clause fee should be used.');
        self::assertSame('500.00', $rows[0]['tariff_amount'], 'The preset amount is still echoed back.');
        self::assertFalse($rows[0]['insurance_clause']['has_tariff_number']);
    }

    #[Test]
    public function aFlatClauseIgnoresItsTariffNumberEntirely(): void
    {
        $service = $this->buildService([
            ['id' => 14, 'hasTariffNumber' => false, 'tariffNumber' => 99.0, 'clauseFlatAmount' => 2.0, 'presetAmount' => 300.0],
        ]);

        self::assertSame('2.00', $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    // -----------------------------------------------------------------------
    // Earthquake zone override
    // -----------------------------------------------------------------------

    #[Test]
    public function earthquakeClauseUsesTheSettlementZoneTariffNumber(): void
    {
        $service = $this->buildService(
            [['id' => 6, 'tariffNumber' => 0.0, 'presetAmount' => 140000.0]],
            [],
            $this->makeSettlementInZone(0.035)
        );

        $rows = $this->clauseRows($service->getTariffPresets(settlementId: 1));

        // 140000 * 0.035 / 100 - the clause's own 0.0 rate is overridden.
        self::assertSame('49.00', $rows[0]['line_total']);
        self::assertSame(0.035, $rows[0]['insurance_clause']['tariff_number'], 'The zone rate is echoed in the payload.');
    }

    #[Test]
    public function withoutASettlementTheEarthquakeClauseKeepsItsOwnZeroRate(): void
    {
        $service = $this->buildService([
            ['id' => 6, 'tariffNumber' => 0.0, 'presetAmount' => 140000.0],
        ]);

        $rows = $this->clauseRows($service->getTariffPresets());

        self::assertSame('0.00', $rows[0]['line_total']);
        self::assertSame(0.0, $rows[0]['insurance_clause']['tariff_number']);
    }

    #[Test]
    public function aSettlementWithNoEarthquakeZoneDoesNotOverrideTheRate(): void
    {
        $service = $this->buildService(
            [['id' => 6, 'tariffNumber' => 0.02, 'presetAmount' => 100000.0]],
            [],
            null // repository returns null for the settlement
        );

        // 100000 * 0.02 / 100
        self::assertSame('20.00', $this->clauseRows($service->getTariffPresets(settlementId: 999))[0]['line_total']);
    }

    #[Test]
    public function theOverrideOnlyAppliesToTheClauseNamedByEarthquakeIdConfig(): void
    {
        $service = $this->buildService(
            [
                ['id' => 6, 'tariffNumber' => 0.0, 'presetAmount' => 100000.0],
                ['id' => 7, 'tariffNumber' => 0.52, 'presetAmount' => 100000.0],
            ],
            [],
            $this->makeSettlementInZone(0.048)
        );

        $rows = $this->clauseRows($service->getTariffPresets(settlementId: 1));

        self::assertSame('48.00', $rows[0]['line_total'], 'Clause 6 is overridden.');
        self::assertSame('520.00', $rows[1]['line_total'], 'Clause 7 keeps its own rate.');
    }

    // -----------------------------------------------------------------------
    // The clause-13 minimum line total
    // -----------------------------------------------------------------------

    /**
     * The floor is not hardcoded to clause 13 - it applies to whichever clause id the
     * THEFT_DAMAGE_CLAUSE_ID app config names. The 1.02 value IS hardcoded.
     */
    #[DataProvider('theftDamageFloorCases')]
    #[Test]
    public function theftDamageClauseIsFlooredAtOneOhTwo(float $presetAmount, float $tariffNumber, string $expected): void
    {
        $service = $this->buildService([
            ['id' => 13, 'tariffNumber' => $tariffNumber, 'presetAmount' => $presetAmount],
        ]);

        self::assertSame($expected, $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    /** @return iterable<string, array{float, float, string}> */
    public static function theftDamageFloorCases(): iterable
    {
        // Below the floor: 10000 * 0.007 / 100 = 0.70 -> lifted to 1.02
        yield 'below the floor is lifted' => [10000.0, 0.007, '1.02'];
        // Exactly the floor: the guard is a strict <, so it is left alone
        yield 'exactly at the floor is untouched' => [14571.428571428572, 0.007, '1.02'];
        // Above the floor: the seeded amount, 28571 * 0.007 / 100 = 1.99997
        yield 'above the floor is untouched' => [28571.0, 0.007, '2.00'];
        yield 'a zero amount is lifted to the floor' => [0.0, 0.007, '1.02'];
    }

    #[Test]
    public function theFloorNeverAppliesToAFlatClause(): void
    {
        $service = $this->buildService([
            ['id' => 13, 'hasTariffNumber' => false, 'clauseFlatAmount' => 0.5, 'presetAmount' => 10.0],
        ]);

        self::assertSame('0.50', $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    #[Test]
    public function theFloorIsDisabledWhenTheConfigRowIsMissing(): void
    {
        $service = $this->buildService(
            [['id' => 13, 'tariffNumber' => 0.007, 'presetAmount' => 10000.0]],
            ['THEFT_DAMAGE_CLAUSE_ID' => null]
        );

        self::assertSame('0.70', $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    #[Test]
    public function theFloorFollowsTheConfiguredClauseId(): void
    {
        $service = $this->buildService(
            [['id' => 7, 'tariffNumber' => 0.007, 'presetAmount' => 10000.0]],
            ['THEFT_DAMAGE_CLAUSE_ID' => '7']
        );

        self::assertSame('1.02', $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    // -----------------------------------------------------------------------
    // Flood clause filtering by distance to water
    // -----------------------------------------------------------------------

    #[DataProvider('floodFilterCases')]
    #[Test]
    public function floodClausesAreFilteredByDistanceToWater(?int $distanceToWaterId, array $expectedClauseIds): void
    {
        $service = $this->buildService([
            ['id' => 4, 'tariffNumber' => 0.03, 'presetAmount' => 20000.0],
            ['id' => 5, 'tariffNumber' => 0.007, 'presetAmount' => 2.0],
        ]);

        $ids = array_map(
            static fn (array $row) => $row['insurance_clause']['id'],
            $this->clauseRows($service->getTariffPresets(distanceToWaterId: $distanceToWaterId))
        );

        self::assertSame($expectedClauseIds, $ids);
    }

    /** @return iterable<string, array{?int, list<int>}> */
    public static function floodFilterCases(): iterable
    {
        // water_distances id 1 = "Обекти до 500 метра" -> the >500m clause (5) is dropped
        yield 'within 500m drops the over-500m clause' => [1, [4]];
        // any other id -> the <500m clause (4) is dropped
        yield 'over 500m drops the under-500m clause' => [2, [5]];
        yield 'no distance given keeps both flood clauses' => [null, [4, 5]];
    }

    // -----------------------------------------------------------------------
    // App config fallbacks
    // -----------------------------------------------------------------------

    #[Test]
    public function missingDiscountAndTaxConfigsFallBackToZero(): void
    {
        $service = $this->buildService(
            [['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 120000.0]],
            ['DISCOUNT_PERCENTS' => null, 'TAX_PERCENTS' => null]
        );

        $preset = $service->getTariffPresets()[0];

        self::assertSame(0, $preset['discount_percent']);
        self::assertSame(0, $preset['tax_percent']);
        self::assertSame('132.00', $preset['statistics']['total_premium']);
        self::assertSame('132.00', $preset['statistics']['discounted_premium']);
        self::assertSame('0.00', $preset['statistics']['tax_amount']);
        self::assertSame('132.00', $preset['statistics']['total_amount']);
    }

    // -----------------------------------------------------------------------
    // Totals, discount and tax
    // -----------------------------------------------------------------------

    #[Test]
    public function discountIsAppliedBeforeTaxAndTaxIsChargedOnTheDiscountedPremium(): void
    {
        $service = $this->buildService([
            ['id' => 1, 'tariffNumber' => 0.1, 'presetAmount' => 100000.0],
        ]);

        $statistics = $service->getTariffPresets()[0]['statistics'];

        // 100000 * 0.1/100 = 100.00; -40% = 60.00; +2% tax on 60 = 1.20
        self::assertSame('100.00', $statistics['total_premium']);
        self::assertSame('60.00', $statistics['discounted_premium']);
        self::assertSame('1.20', $statistics['tax_amount']);
        self::assertSame('61.20', $statistics['total_amount']);
    }

    #[Test]
    public function seededPresetOneProducesItsKnownTotals(): void
    {
        $service = $this->buildService(self::seededPresetOneClauses());

        $statistics = $service->getTariffPresets()[0]['statistics'];

        self::assertSame('192.70', $statistics['total_premium']);
        self::assertSame('115.62', $statistics['discounted_premium']);
        self::assertSame('2.31', $statistics['tax_amount']);
        self::assertSame('117.93', $statistics['total_amount']);
    }

    #[Test]
    public function everyMonetaryValueIsATwoDecimalString(): void
    {
        $service = $this->buildService(self::seededPresetOneClauses());
        $preset = $service->getTariffPresets()[0];

        foreach ($preset['statistics'] as $key => $value) {
            self::assertIsString($value, sprintf('statistics.%s should be a string', $key));
            self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $value);
        }

        foreach ($preset['tariff_preset_clauses'] as $row) {
            self::assertIsString($row['tariff_amount']);
            self::assertIsString($row['line_total']);
            self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $row['line_total']);
        }
    }

    #[Test]
    public function anEmptyPresetProducesZeroTotals(): void
    {
        $service = $this->buildService([]);
        $preset = $service->getTariffPresets()[0];

        self::assertSame([], $preset['tariff_preset_clauses']);
        self::assertSame('0.00', $preset['statistics']['total_premium']);
        self::assertSame('0.00', $preset['statistics']['total_amount']);
    }

    #[Test]
    public function veryLargeInsuredAmountsStayExact(): void
    {
        $service = $this->buildService([
            ['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 1000000000.0],
        ]);

        self::assertSame('1100000.00', $this->clauseRows($service->getTariffPresets())[0]['line_total']);
    }

    // -----------------------------------------------------------------------
    // Rounding
    // -----------------------------------------------------------------------

    /**
     * Characterization test. getTariffPresets() rounds each line to 2dp and then sums
     * the rounded strings, while calculateCustomPackageStatistics() sums the raw floats
     * and rounds once at the end. The same clause set can therefore price differently
     * through the two entry points. Reported as a finding.
     */
    #[Test]
    public function presetAndCustomPathsDisagreeOnRounding_KNOWN_GAP(): void
    {
        $clauses = [
            ['id' => 1, 'tariffNumber' => 0.1, 'presetAmount' => 5.0],
            ['id' => 2, 'tariffNumber' => 0.1, 'presetAmount' => 5.0],
            ['id' => 3, 'tariffNumber' => 0.1, 'presetAmount' => 5.0],
        ];

        $presetTotal = $this->buildService($clauses)
            ->getTariffPresets()[0]['statistics']['total_premium'];

        // Each line is 5 * 0.1 / 100 = 0.005.
        $customTotal = $this->buildService($clauses)
            ->calculateCustomPackageStatistics([1 => 5.0, 2 => 5.0, 3 => 5.0])['statistics']['total_premium'];

        self::assertSame('0.03', $presetTotal, 'Preset path: each line rounds to 0.01, then sums.');
        self::assertSame('0.02', $customTotal, 'Custom path: sums 0.015 first, then rounds.');
        self::assertNotSame($presetTotal, $customTotal);
    }

    /**
     * total_amount is derived from unrounded intermediates, so it is not always equal to
     * the printed discounted_premium plus the printed tax_amount.
     */
    #[Test]
    public function totalAmountIsDerivedFromUnroundedIntermediates_KNOWN_GAP(): void
    {
        $service = $this->buildService(
            [['id' => 1, 'tariffNumber' => 0.1, 'presetAmount' => 100250.0]],
            ['DISCOUNT_PERCENTS' => '0', 'TAX_PERCENTS' => '3']
        );

        $statistics = $service->getTariffPresets()[0]['statistics'];

        $printedSum = number_format(
            (float) $statistics['discounted_premium'] + (float) $statistics['tax_amount'],
            2,
            '.',
            ''
        );

        self::assertSame('100.25', $statistics['discounted_premium']);
        self::assertSame('3.01', $statistics['tax_amount']);   // 3.0075 rounded for display
        self::assertSame('103.26', $statistics['total_amount']); // from 103.2575, not 100.25 + 3.01
        self::assertSame('103.26', $printedSum);
    }

    // -----------------------------------------------------------------------
    // Custom package path
    // -----------------------------------------------------------------------

    #[Test]
    public function customPackageSkipsClausesWithNoAmount(): void
    {
        $service = $this->buildService([
            ['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 0.0],
            ['id' => 2, 'tariffNumber' => 0.13, 'presetAmount' => 0.0],
        ]);

        $result = $service->calculateCustomPackageStatistics([1 => 120000.0, 2 => 0]);

        // Only clause 1 contributes: 120000 * 0.11 / 100
        self::assertSame('132.00', $result['statistics']['total_premium']);
    }

    #[Test]
    public function customPackageUsesTheCallerAmountForPercentageClauses(): void
    {
        $service = $this->buildService([
            ['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 999999.0],
        ]);

        $result = $service->calculateCustomPackageStatistics([1 => 200000.0]);

        // The preset amount is irrelevant here; the caller's 200000 is used.
        self::assertSame('220.00', $result['statistics']['total_premium']);
    }

    #[Test]
    public function customPackageStillChargesTheFlatFeeRegardlessOfTheAmountPassed(): void
    {
        $service = $this->buildService([
            ['id' => 15, 'hasTariffNumber' => false, 'clauseFlatAmount' => 2.0],
        ]);

        // The caller's 5000 is only used to decide the clause is included at all.
        $result = $service->calculateCustomPackageStatistics([15 => 5000.0]);

        self::assertSame('2.00', $result['statistics']['total_premium']);
    }

    #[Test]
    public function customPackageReturnsDiscountAndTaxPercentages(): void
    {
        $service = $this->buildService([['id' => 1, 'tariffNumber' => 0.11]]);

        $result = $service->calculateCustomPackageStatistics([]);

        self::assertSame(40.0, $result['discount_percent']);
        self::assertSame(2.0, $result['tax_percent']);
        self::assertSame('0.00', $result['statistics']['total_premium']);
    }
}
