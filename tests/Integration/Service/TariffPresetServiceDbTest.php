<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Settlement;
use App\Repository\SettlementRepository;
use App\Service\TariffPresetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Golden-master pricing against the real seeded reference data, so the unit tests
 * cannot drift away from what the seeders actually produce.
 */
#[CoversClass(TariffPresetService::class)]
final class TariffPresetServiceDbTest extends KernelTestCase
{
    private TariffPresetService $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(TariffPresetService::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function settlementInZone(int $zoneId): Settlement
    {
        /** @var SettlementRepository $repository */
        $repository = self::getContainer()->get(SettlementRepository::class);

        $settlement = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Settlement::class, 's')
            ->where('s.earthquakeZone = :zone')
            ->setParameter('zone', $zoneId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($settlement, sprintf('No seeded settlement in earthquake zone %d.', $zoneId));

        return $settlement;
    }

    #[Test]
    public function allFourSeededPresetsAreReturned(): void
    {
        $presets = $this->service->getTariffPresets();

        self::assertCount(4, $presets);
        self::assertSame(['Пакет 1', 'Пакет 2', 'Пакет 3', 'Пакет 4'], array_column($presets, 'name'));

        foreach ($presets as $preset) {
            self::assertCount(15, $preset['tariff_preset_clauses'], 'Every preset carries all 15 clauses.');
        }
    }

    #[Test]
    public function presetOneWithoutASettlementMatchesTheKnownTotals(): void
    {
        $preset = $this->service->getTariffPresets()[0];

        self::assertSame('192.70', $preset['statistics']['total_premium']);
        self::assertSame('115.62', $preset['statistics']['discounted_premium']);
        self::assertSame('2.31', $preset['statistics']['tax_amount']);
        self::assertSame('117.93', $preset['statistics']['total_amount']);

        self::assertSame(40.0, $preset['discount_percent'], 'Seeded DISCOUNT_PERCENTS');
        self::assertSame(2.0, $preset['tax_percent'], 'Seeded TAX_PERCENTS');
    }

    #[Test]
    public function theSeededEarthquakeClauseIsFreeUntilASettlementIsChosen(): void
    {
        $clauses = $this->service->getTariffPresets()[0]['tariff_preset_clauses'];
        $earthquake = $this->clauseById($clauses, 6);

        self::assertSame('140000.00', $earthquake['tariff_amount']);
        self::assertSame('0.00', $earthquake['line_total'], 'Seeded clause 6 has tariff_number 0.0.');
    }

    #[Test]
    public function aZoneThreeSettlementRepricesTheEarthquakeClause(): void
    {
        $settlement = $this->settlementInZone(3);

        $preset = $this->service->getTariffPresets($settlement->getId())[0];
        $earthquake = $this->clauseById($preset['tariff_preset_clauses'], 6);

        // 140000 * 0.035 / 100
        self::assertSame(0.035, $earthquake['insurance_clause']['tariff_number']);
        self::assertSame('49.00', $earthquake['line_total']);

        // 192.70 + 49.00
        self::assertSame('241.70', $preset['statistics']['total_premium']);
        self::assertSame('145.02', $preset['statistics']['discounted_premium']);
        self::assertSame('2.90', $preset['statistics']['tax_amount']);
        self::assertSame('147.92', $preset['statistics']['total_amount']);
    }

    #[Test]
    public function eachEarthquakeZoneAppliesItsOwnRate(): void
    {
        foreach ([1 => 0.015, 2 => 0.025, 3 => 0.035, 4 => 0.048] as $zoneId => $expectedRate) {
            $settlement = $this->settlementInZone($zoneId);
            $preset = $this->service->getTariffPresets($settlement->getId())[0];
            $earthquake = $this->clauseById($preset['tariff_preset_clauses'], 6);

            self::assertSame(
                $expectedRate,
                $earthquake['insurance_clause']['tariff_number'],
                sprintf('Zone %d rate', $zoneId)
            );
            self::assertSame(
                number_format(140000 * $expectedRate / 100, 2, '.', ''),
                $earthquake['line_total']
            );
        }
    }

    #[Test]
    public function distanceToWaterDropsExactlyOneFloodClause(): void
    {
        $within500 = $this->service->getTariffPresets(null, 1)[0]['tariff_preset_clauses'];
        $over500 = $this->service->getTariffPresets(null, 2)[0]['tariff_preset_clauses'];
        $unspecified = $this->service->getTariffPresets()[0]['tariff_preset_clauses'];

        self::assertCount(14, $within500);
        self::assertCount(14, $over500);
        self::assertCount(15, $unspecified);

        $ids = static fn (array $rows) => array_column(array_column($rows, 'insurance_clause'), 'id');

        self::assertContains(4, $ids($within500), 'Within 500m keeps the under-500m clause.');
        self::assertNotContains(5, $ids($within500), 'Within 500m drops the over-500m clause.');

        self::assertNotContains(4, $ids($over500));
        self::assertContains(5, $ids($over500));
    }

    #[Test]
    public function theSeededTheftDamageClauseSitsJustAboveTheFloor(): void
    {
        $clauses = $this->service->getTariffPresets()[0]['tariff_preset_clauses'];
        $theft = $this->clauseById($clauses, 13);

        // 28571 * 0.007 / 100 = 1.99997, so the 1.02 floor never actually fires on
        // seeded data - the floor is only reachable with a sum insured below ~14571.
        self::assertSame('28571.00', $theft['tariff_amount']);
        self::assertSame('2.00', $theft['line_total']);
    }

    #[Test]
    public function seededFlatClausesChargeTwoEuroRegardlessOfPreset(): void
    {
        foreach ($this->service->getTariffPresets() as $preset) {
            foreach ([14, 15] as $clauseId) {
                $clause = $this->clauseById($preset['tariff_preset_clauses'], $clauseId);

                self::assertFalse($clause['insurance_clause']['has_tariff_number']);
                self::assertSame('2.00', $clause['line_total'], sprintf('Clause %d is a flat fee.', $clauseId));
            }
        }
    }

    #[Test]
    public function customPackageStatisticsPriceTheSameClauseTheSameWay(): void
    {
        $result = $this->service->calculateCustomPackageStatistics([1 => 120000.0]);

        // 120000 * 0.11 / 100 = 132.00
        self::assertSame('132.00', $result['statistics']['total_premium']);
        self::assertSame('79.20', $result['statistics']['discounted_premium']);
        self::assertSame('80.78', $result['statistics']['total_amount']);
    }

    private function clauseById(array $clauses, int $id): array
    {
        foreach ($clauses as $clause) {
            if ($clause['insurance_clause']['id'] === $id) {
                return $clause;
            }
        }

        self::fail(sprintf('Clause %d not present in the preset.', $id));
    }
}
