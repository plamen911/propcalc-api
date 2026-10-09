<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TariffPresetRepository;
use App\Repository\TariffPresetClauseRepository;
use App\Repository\SettlementRepository;
use App\Repository\AppConfigRepository;
use App\Repository\InsuranceClauseRepository;

class TariffPresetService
{
    private TariffPresetRepository $tariffPresetRepository;
    private TariffPresetClauseRepository $tariffPresetClauseRepository;
    private SettlementRepository $settlementRepository;
    private AppConfigRepository $appConfigRepository;
    private InsuranceClauseRepository $insuranceClauseRepository;

    public function __construct(
        TariffPresetRepository $tariffPresetRepository,
        TariffPresetClauseRepository $tariffPresetClauseRepository,
        SettlementRepository $settlementRepository,
        AppConfigRepository $appConfigRepository,
        InsuranceClauseRepository $insuranceClauseRepository
    ) {
        $this->tariffPresetRepository = $tariffPresetRepository;
        $this->tariffPresetClauseRepository = $tariffPresetClauseRepository;
        $this->settlementRepository = $settlementRepository;
        $this->appConfigRepository = $appConfigRepository;
        $this->insuranceClauseRepository = $insuranceClauseRepository;
    }

    /**
     * The catalogue as the quote form needs it: presets for the location, optionally
     * narrowed to those whose building sum insured covers the property area.
     *
     * Serves both the legacy admin-namespaced route and the public
     * /api/v1/form-data/tariff-presets, so the two cannot drift apart.
     *
     * @return list<array<string, mixed>>
     */
    public function getTariffPresetsForQuote(
        ?int $settlementId = null,
        ?int $distanceToWaterId = null,
        int $areaSqMeters = 0
    ): array {
        $data = $this->getTariffPresets($settlementId, $distanceToWaterId);

        if ($areaSqMeters <= 0) {
            return $data;
        }

        // Keep presets whose FIRST clause (the building sum insured) is at least
        // area * 1000, then cap the list at five.
        return array_slice(
            array_values(
                array_filter($data, function (array $item) use ($areaSqMeters) {
                    return !empty($item['tariff_preset_clauses'])
                        && is_array($item['tariff_preset_clauses'])
                        && isset($item['tariff_preset_clauses'][0]['tariff_amount'])
                        && ((int) $item['tariff_preset_clauses'][0]['tariff_amount']) >= $areaSqMeters * 1000;
                })
            ),
            0,
            5
        );
    }

    /**
     * Get a list of tariff presets with their clauses
     */
    public function getTariffPresets(?int $settlementId = null, ?int $distanceToWaterId = null): array
    {
        $tariffPresets = $this->tariffPresetRepository->findAll();

        $zoneConfig = $this->resolveZoneConfig($settlementId, $distanceToWaterId);

        // Fetch all tariff preset clauses with their insurance clauses in a single query
        $allClauses = $this->tariffPresetClauseRepository->findByTariffPresetsWithInsuranceClauses($tariffPresets);

        $data = [];
        foreach ($tariffPresets as $preset) {
            $presetId = $preset->getId();
            $presetData = [
                'id' => $presetId,
                'name' => $preset->getName(),
                'active' => $preset->isActive(),
                'position' => $preset->getPosition(),
                'discount_percent' => $zoneConfig['discountPercent'],
                'tax_percent' => $zoneConfig['taxPercent'],
                'tariff_preset_clauses' => [],
            ];

            $tariffPresetClauses = $allClauses[$presetId] ?? [];

            foreach ($tariffPresetClauses as $clause) {
                $insuranceClause = $clause->getInsuranceClause();
                $insuranceClauseId = $insuranceClause->getId();

                if ($zoneConfig['floodZoneIdToSkip'] !== null && $insuranceClauseId === $zoneConfig['floodZoneIdToSkip']) {
                    continue;
                }

                $tariffNumber = $insuranceClause->getTariffNumber();
                if ($zoneConfig['earthquakeId'] !== null
                    && $zoneConfig['earthquakeTariffNumber'] !== null
                    && $insuranceClauseId === $zoneConfig['earthquakeId']
                ) {
                    $tariffNumber = $zoneConfig['earthquakeTariffNumber'];
                }

                // Calculate line_total
                $lineTotal = $insuranceClause->getHasTariffNumber()
                    ? $clause->getTariffAmount() * $tariffNumber / 100.0
                    : $insuranceClause->getTariffAmount();

                if ($insuranceClause->getHasTariffNumber() && $zoneConfig['theftDamageClauseId'] !== null && $insuranceClauseId === $zoneConfig['theftDamageClauseId'] && $lineTotal < 1.02) {
                    $lineTotal = 1.02;
                }

                // Round once, here, and use the same value for display and for the sum.
                // The premium is the sum of the lines the customer is shown.
                $lineTotal = round($lineTotal, 2);

                $presetData['tariff_preset_clauses'][] = [
                    'id' => $clause->getId(),
                    'insurance_clause' => [
                        'id' => $insuranceClauseId,
                        'name' => $insuranceClause->getName(),
                        'description' => $insuranceClause->getDescription(),
                        'has_tariff_number' => $insuranceClause->getHasTariffNumber(),
                        'tariff_number' => $tariffNumber,
                        'allow_custom_amount' => $insuranceClause->getAllowCustomAmount(),
                        'position' => $clause->getPosition(),
                    ],
                    'tariff_amount' => number_format($clause->getTariffAmount(), 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ];
            }

            // Calculate totals
            $totalPremium = 0.0;
            foreach ($presetData['tariff_preset_clauses'] as $clause) {
                $totalPremium += (float) $clause['line_total'];
            }

            $presetData['statistics'] = $this->calculatePremiumBreakdown(
                $totalPremium,
                $zoneConfig['discountPercent'],
                $zoneConfig['taxPercent']
            );

            $data[] = $presetData;
        }

        return $data;
    }

    /**
     * Calculate statistics for a custom package
     */
    public function calculateCustomPackageStatistics(array $customClauseAmounts, ?int $settlementId = null, ?int $distanceToWaterId = null): array
    {
        $zoneConfig = $this->resolveZoneConfig($settlementId, $distanceToWaterId);

        // Calculate totals
        $totalPremium = 0;

        $clauseIds = array_keys($customClauseAmounts);
        $insuranceClauses = [];

        if (!empty($clauseIds)) {
            $insuranceClauses = $this->insuranceClauseRepository->findBy(['id' => $clauseIds]);
        }

        foreach ($insuranceClauses as $insuranceClause) {
            $insuranceClauseId = $insuranceClause->getId();
            $customAmount = isset($customClauseAmounts[$insuranceClauseId]) && $customClauseAmounts[$insuranceClauseId] !== ''
                ? (float) $customClauseAmounts[$insuranceClauseId]
                : 0;

            if ($customAmount <= 0 || ($zoneConfig['floodZoneIdToSkip'] !== null && $insuranceClauseId === $zoneConfig['floodZoneIdToSkip'])) {
                continue;
            }

            $tariffNumber = $insuranceClause->getTariffNumber();
            if ($zoneConfig['earthquakeId'] !== null
                && $zoneConfig['earthquakeTariffNumber'] !== null
                && $insuranceClauseId === $zoneConfig['earthquakeId']
            ) {
                $tariffNumber = $zoneConfig['earthquakeTariffNumber'];
            }

            $lineTotal = $insuranceClause->getHasTariffNumber()
                ? $customAmount * $tariffNumber / 100.0
                : $insuranceClause->getTariffAmount();

            if ($insuranceClause->getHasTariffNumber() && $zoneConfig['theftDamageClauseId'] !== null && $insuranceClauseId === $zoneConfig['theftDamageClauseId'] && $lineTotal < 1.02) {
                $lineTotal = 1.02;
            }

            // Rounded per line, exactly as the preset path does, so the same basket
            // prices identically whichever entry point built it.
            $totalPremium += round($lineTotal, 2);
        }

        return [
            'discount_percent' => $zoneConfig['discountPercent'],
            'tax_percent' => $zoneConfig['taxPercent'],
            'statistics' => $this->calculatePremiumBreakdown(
                $totalPremium,
                $zoneConfig['discountPercent'],
                $zoneConfig['taxPercent']
            ),
        ];
    }

    private function resolveZoneConfig(?int $settlementId, ?int $distanceToWaterId): array
    {
        $configNames = ['EARTHQUAKE_ID', 'DISCOUNT_PERCENTS', 'TAX_PERCENTS', 'THEFT_DAMAGE_CLAUSE_ID'];
        if ($distanceToWaterId !== null) {
            $configNames[] = 'FLOOD_LT_500_M_ID';
            $configNames[] = 'FLOOD_GT_500_M_ID';
        }
        $appConfigs = $this->appConfigRepository->findByNames($configNames);

        $earthquakeId = null;
        $earthquakeTariffNumber = null;

        if ($settlementId !== null && isset($appConfigs['EARTHQUAKE_ID'])) {
            $earthquakeId = (int) $appConfigs['EARTHQUAKE_ID']->getValue();

            $settlement = $this->settlementRepository->find($settlementId);
            if ($settlement && $settlement->getEarthquakeZone()) {
                $earthquakeTariffNumber = $settlement->getEarthquakeZone()->getTariffNumber();
            }
        }

        $floodZoneIdToSkip = null;

        if ($distanceToWaterId !== null
            && isset($appConfigs['FLOOD_LT_500_M_ID'])
            && isset($appConfigs['FLOOD_GT_500_M_ID'])) {
            $floodZoneIdToSkip = $distanceToWaterId === 1
                ? (int) $appConfigs['FLOOD_GT_500_M_ID']->getValue()
                : (int) $appConfigs['FLOOD_LT_500_M_ID']->getValue();
        }

        $discountPercent = isset($appConfigs['DISCOUNT_PERCENTS'])
            ? (float) $appConfigs['DISCOUNT_PERCENTS']->getValue()
            : 0;
        $taxPercent = isset($appConfigs['TAX_PERCENTS'])
            ? (float) $appConfigs['TAX_PERCENTS']->getValue()
            : 0;

        $theftDamageClauseId = isset($appConfigs['THEFT_DAMAGE_CLAUSE_ID'])
            ? (int) $appConfigs['THEFT_DAMAGE_CLAUSE_ID']->getValue()
            : null;

        return [
            'earthquakeId' => $earthquakeId,
            'earthquakeTariffNumber' => $earthquakeTariffNumber,
            'floodZoneIdToSkip' => $floodZoneIdToSkip,
            'discountPercent' => $discountPercent,
            'taxPercent' => $taxPercent,
            'theftDamageClauseId' => $theftDamageClauseId,
        ];
    }

    /**
     * Rounds at every step, so the printed parts always add up to the printed total.
     *
     * This is deliberately the same arithmetic as StatisticsService::calculate() and as
     * the client's calc-statistics.js, which is what the customer is actually charged
     * and what the policy PDF and confirmation email state. Deriving the total from
     * unrounded intermediates instead put this breakdown a cent away from the charged
     * figure on roughly a quarter of quotes.
     */
    private function calculatePremiumBreakdown(float $totalPremium, float $discountPercent, float $taxPercent): array
    {
        $totalPremium = round($totalPremium, 2);
        $discountedPremium = round($totalPremium * (1 - $discountPercent / 100), 2);
        $taxAmount = round($discountedPremium * ($taxPercent / 100), 2);
        $totalAmount = round($discountedPremium + $taxAmount, 2);

        return [
            'total_premium' => number_format($totalPremium, 2, '.', ''),
            'discounted_premium' => number_format($discountedPremium, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ];
    }
}
