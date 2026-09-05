<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\AppConfig;
use App\Entity\EarthquakeZone;
use App\Entity\InsuranceClause;
use App\Entity\Settlement;
use App\Entity\TariffPreset;
use App\Entity\TariffPresetClause;
use App\Repository\AppConfigRepository;
use App\Repository\InsuranceClauseRepository;
use App\Repository\SettlementRepository;
use App\Repository\TariffPresetClauseRepository;
use App\Repository\TariffPresetRepository;
use App\Service\TariffPresetService;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Builders for TariffPresetService unit tests.
 *
 * Entity ids are database-generated and have no setters. Rather than reaching into
 * private properties with reflection - which couples tests to entity internals and
 * ends up asserting the reflection worked rather than that the pricing is right - the
 * entities are mocked at their public accessors. The service only ever reads them, so
 * a mock is a faithful stand-in and the arithmetic inputs stay explicit.
 */
trait PricingDoubles
{
    /** @var array<string, string> */
    private array $appConfigValues = [];

    /**
     * @param list<array<string, mixed>> $clauseSpecs
     */
    private function buildService(
        array $clauseSpecs,
        array $configOverrides = [],
        ?Settlement $settlement = null
    ): TariffPresetService {
        $configs = array_merge([
            'EARTHQUAKE_ID' => '6',
            'FLOOD_LT_500_M_ID' => '4',
            'FLOOD_GT_500_M_ID' => '5',
            'THEFT_DAMAGE_CLAUSE_ID' => '13',
            'DISCOUNT_PERCENTS' => '40',
            'TAX_PERCENTS' => '2',
        ], $configOverrides);

        foreach ($configOverrides as $name => $value) {
            if ($value === null) {
                unset($configs[$name]);
            }
        }

        $preset = $this->makePreset();
        $presetClauses = array_map(fn (array $spec) => $this->makePresetClause($spec), $clauseSpecs);

        $presetRepository = $this->createMock(TariffPresetRepository::class);
        $presetRepository->method('findAll')->willReturn([$preset]);

        $presetClauseRepository = $this->createMock(TariffPresetClauseRepository::class);
        $presetClauseRepository->method('findByTariffPresetsWithInsuranceClauses')
            ->willReturn([1 => $presetClauses]);

        $appConfigRepository = $this->createMock(AppConfigRepository::class);
        $appConfigRepository->method('findByNames')->willReturnCallback(
            static function (array $names) use ($configs): array {
                $found = [];
                foreach ($names as $name) {
                    if (isset($configs[$name])) {
                        $config = new AppConfig();
                        $config->setName($name);
                        $config->setValue($configs[$name]);
                        $found[$name] = $config;
                    }
                }

                return $found;
            }
        );

        $settlementRepository = $this->createMock(SettlementRepository::class);
        $settlementRepository->method('find')->willReturn($settlement);

        $insuranceClauseRepository = $this->createMock(InsuranceClauseRepository::class);
        $insuranceClauseRepository->method('findBy')->willReturn(
            array_map(static fn (TariffPresetClause $c) => $c->getInsuranceClause(), $presetClauses)
        );

        return new TariffPresetService(
            $presetRepository,
            $presetClauseRepository,
            $settlementRepository,
            $appConfigRepository,
            $insuranceClauseRepository
        );
    }

    private function makePreset(): TariffPreset&MockObject
    {
        $preset = $this->createMock(TariffPreset::class);
        $preset->method('getId')->willReturn(1);
        $preset->method('getName')->willReturn('Пакет 1');
        $preset->method('isActive')->willReturn(true);
        $preset->method('getPosition')->willReturn(1);

        return $preset;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function makePresetClause(array $spec): TariffPresetClause&MockObject
    {
        $clause = $this->createMock(InsuranceClause::class);
        $clause->method('getId')->willReturn($spec['id']);
        $clause->method('getName')->willReturn($spec['name'] ?? 'Clause ' . $spec['id']);
        $clause->method('getDescription')->willReturn($spec['description'] ?? null);
        $clause->method('getHasTariffNumber')->willReturn($spec['hasTariffNumber'] ?? true);
        $clause->method('getTariffNumber')->willReturn($spec['tariffNumber'] ?? 0.0);
        $clause->method('getAllowCustomAmount')->willReturn($spec['allowCustomAmount'] ?? true);
        // The flat fee used when hasTariffNumber is false.
        $clause->method('getTariffAmount')->willReturn($spec['clauseFlatAmount'] ?? 0.0);

        $presetClause = $this->createMock(TariffPresetClause::class);
        $presetClause->method('getId')->willReturn($spec['presetClauseId'] ?? $spec['id']);
        $presetClause->method('getInsuranceClause')->willReturn($clause);
        // The sum insured this preset assigns to the clause.
        $presetClause->method('getTariffAmount')->willReturn($spec['presetAmount'] ?? 0.0);
        $presetClause->method('getPosition')->willReturn($spec['position'] ?? $spec['id']);

        return $presetClause;
    }

    private function makeSettlementInZone(float $zoneTariffNumber): Settlement&MockObject
    {
        $zone = $this->createMock(EarthquakeZone::class);
        $zone->method('getTariffNumber')->willReturn($zoneTariffNumber);

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getEarthquakeZone')->willReturn($zone);

        return $settlement;
    }

    /**
     * The seeded preset 1: all 15 clauses with their real tariff numbers and amounts.
     *
     * @return list<array<string, mixed>>
     */
    private static function seededPresetOneClauses(): array
    {
        return [
            ['id' => 1, 'tariffNumber' => 0.11, 'presetAmount' => 120000.0],
            ['id' => 2, 'tariffNumber' => 0.13, 'presetAmount' => 20000.0],
            ['id' => 3, 'tariffNumber' => 1.2, 'presetAmount' => 0.0],
            ['id' => 4, 'tariffNumber' => 0.03, 'presetAmount' => 20000.0],
            ['id' => 5, 'tariffNumber' => 0.007, 'presetAmount' => 2.0],
            ['id' => 6, 'tariffNumber' => 0.0, 'presetAmount' => 140000.0],
            ['id' => 7, 'tariffNumber' => 0.52, 'presetAmount' => 0.0],
            ['id' => 8, 'tariffNumber' => 0.14, 'presetAmount' => 5000.0],
            ['id' => 9, 'tariffNumber' => 0.15, 'presetAmount' => 3000.0],
            ['id' => 10, 'tariffNumber' => 0.14, 'presetAmount' => 3000.0],
            ['id' => 11, 'tariffNumber' => 0.07, 'presetAmount' => 0.0],
            ['id' => 12, 'tariffNumber' => 0.07, 'presetAmount' => 10000.0],
            ['id' => 13, 'tariffNumber' => 0.007, 'presetAmount' => 28571.0],
            ['id' => 14, 'tariffNumber' => 0.0, 'presetAmount' => 300.0, 'hasTariffNumber' => false, 'clauseFlatAmount' => 2.0],
            ['id' => 15, 'tariffNumber' => 0.0, 'presetAmount' => 500.0, 'hasTariffNumber' => false, 'clauseFlatAmount' => 2.0],
        ];
    }
}
