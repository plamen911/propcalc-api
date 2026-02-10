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

                // For insurance_clause_id = 13, ensure line_total is at least 1.02
                if ($insuranceClause->getHasTariffNumber() && $insuranceClauseId === 13 && $lineTotal < 1.02) {
                    $lineTotal = 1.02;
                }

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
            $totalPremium = 0;
            foreach ($presetData['tariff_preset_clauses'] as $clause) {
                $totalPremium += (float) str_replace(',', '', $clause['line_total']);
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

            $totalPremium += $lineTotal;
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
        $configNames = ['EARTHQUAKE_ID', 'DISCOUNT_PERCENTS', 'TAX_PERCENTS'];
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

        return [
            'earthquakeId' => $earthquakeId,
            'earthquakeTariffNumber' => $earthquakeTariffNumber,
            'floodZoneIdToSkip' => $floodZoneIdToSkip,
            'discountPercent' => $discountPercent,
            'taxPercent' => $taxPercent,
        ];
    }

    private function calculatePremiumBreakdown(float $totalPremium, float $discountPercent, float $taxPercent): array
    {
        $discountedPremium = $totalPremium * (1 - $discountPercent / 100);
        $taxAmount = $discountedPremium * ($taxPercent / 100);
        $totalAmount = $discountedPremium + $taxAmount;

        return [
            'total_premium' => number_format($totalPremium, 2, '.', ''),
            'discounted_premium' => number_format($discountedPremium, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ];
    }
}
