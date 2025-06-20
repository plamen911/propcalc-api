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
     *
     * @param int|null $settlementId Optional settlement ID to filter by
     * @param int|null $distanceToWaterId Optional distance to water ID to filter by
     *
     * @return array List of tariff presets with their clauses
     */
    public function getTariffPresets(?int $settlementId = null, ?int $distanceToWaterId = null): array
    {
        // Get all tariff presets
        $tariffPresets = $this->tariffPresetRepository->findAll();

        // Fetch all required app configs in a single query
        $configNames = ['EARTHQUAKE_ID', 'DISCOUNT_PERCENTS', 'TAX_PERCENTS'];
        if ($distanceToWaterId !== null) {
            $configNames[] = 'FLOOD_LT_500_M_ID';
            $configNames[] = 'FLOOD_GT_500_M_ID';
        }
        $appConfigs = $this->appConfigRepository->findByNames($configNames);

        // Variables for earthquake zone logic
        $earthquakeId = null;
        $earthquakeTariffNumber = null;

        // If settlementId is provided, get the earthquake ID and tariff number
        if ($settlementId !== null && isset($appConfigs['EARTHQUAKE_ID'])) {
            $earthquakeId = (int) $appConfigs['EARTHQUAKE_ID']->getValue();

            // Get the settlement and its earthquake zone tariff number
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

        // Fetch all tariff preset clauses with their insurance clauses in a single query
        $allClauses = $this->tariffPresetClauseRepository->findByTariffPresetsWithInsuranceClauses($tariffPresets);

        // Get discount and tax percentages from app configs
        $discountPercent = isset($appConfigs['DISCOUNT_PERCENTS'])
            ? (float) $appConfigs['DISCOUNT_PERCENTS']->getValue()
            : 0;
        $taxPercent = isset($appConfigs['TAX_PERCENTS'])
            ? (float) $appConfigs['TAX_PERCENTS']->getValue()
            : 0;

        $data = [];
        foreach ($tariffPresets as $preset) {
            $presetId = $preset->getId();
            $presetData = [
                'id' => $presetId,
                'name' => $preset->getName(),
                'active' => $preset->isActive(),
                'position' => $preset->getPosition(),
                'discount_percent' => $discountPercent,
                'tax_percent' => $taxPercent,
                'tariff_preset_clauses' => [],
            ];

            // Get all tariff preset clauses for this preset from the pre-fetched data
            $tariffPresetClauses = $allClauses[$presetId] ?? [];

            foreach ($tariffPresetClauses as $clause) {
                $insuranceClause = $clause->getInsuranceClause();
                $insuranceClauseId = $insuranceClause->getId();

                if ($floodZoneIdToSkip !== null && $insuranceClauseId === $floodZoneIdToSkip) {
                    continue;
                }

                // Check if this is the earthquake clause and we have a tariff number
                $tariffNumber = $insuranceClause->getTariffNumber();
                if ($earthquakeId !== null
                    && $earthquakeTariffNumber !== null
                    && $insuranceClauseId === $earthquakeId
                ) {
                    // Use the earthquake zone tariff number instead
                    $tariffNumber = $earthquakeTariffNumber;
                }

                // Calculate line_total
                $lineTotal = $insuranceClause->getHasTariffNumber()
                    ? $clause->getTariffAmount() * $tariffNumber / 100.0
                    : $insuranceClause->getTariffAmount();

                // For insurance_clause_id = 13, ensure line_total is at least 2.0
                if ($insuranceClause->getHasTariffNumber() && $insuranceClauseId === 13 && $lineTotal < 2.0) {
                    $lineTotal = 2.0;
                }

                $presetData['tariff_preset_clauses'][] = [
                    'id' => $clause->getId(),
                    'insurance_clause' => [
                        'id' => $insuranceClauseId,
                        'name' => $insuranceClause->getName(),
                        'description' => $insuranceClause->getDescription(),
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

            // Calculate discounted premium
            $discountedPremium = $totalPremium * (1 - $discountPercent / 100);

            // Calculate tax
            $taxAmount = $discountedPremium * ($taxPercent / 100);

            // Calculate total amount
            $totalAmount = $discountedPremium + $taxAmount;

            // Add statistics to preset data
            $presetData['statistics'] = [
                'total_premium' => number_format($totalPremium, 2, '.', ''),
                'discounted_premium' => number_format($discountedPremium, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', '')
            ];

            $data[] = $presetData;
        }

        return $data;
    }
    /**
     * Calculate statistics for a custom package
     *
     * @param array $customClauseAmounts Array of custom clause amounts indexed by clause ID
     * @param int|null $settlementId Optional settlement ID to filter by
     * @param int|null $distanceToWaterId Optional distance to water ID to filter by
     *
     * @return array Statistics for the custom package
     */
    public function calculateCustomPackageStatistics(array $customClauseAmounts, ?int $settlementId = null, ?int $distanceToWaterId = null): array
    {
        // Fetch all required app configs in a single query
        $configNames = ['EARTHQUAKE_ID', 'DISCOUNT_PERCENTS', 'TAX_PERCENTS'];
        if ($distanceToWaterId !== null) {
            $configNames[] = 'FLOOD_LT_500_M_ID';
            $configNames[] = 'FLOOD_GT_500_M_ID';
        }
        $appConfigs = $this->appConfigRepository->findByNames($configNames);

        // Variables for earthquake zone logic
        $earthquakeId = null;
        $earthquakeTariffNumber = null;

        // If settlementId is provided, get the earthquake ID and tariff number
        if ($settlementId !== null && isset($appConfigs['EARTHQUAKE_ID'])) {
            $earthquakeId = (int) $appConfigs['EARTHQUAKE_ID']->getValue();

            // Get the settlement and its earthquake zone tariff number
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

        // Get discount and tax percentages from app configs
        $discountPercent = isset($appConfigs['DISCOUNT_PERCENTS'])
            ? (float) $appConfigs['DISCOUNT_PERCENTS']->getValue()
            : 0;
        $taxPercent = isset($appConfigs['TAX_PERCENTS'])
            ? (float) $appConfigs['TAX_PERCENTS']->getValue()
            : 0;

        // Calculate totals
        $totalPremium = 0;

        // Get all insurance clauses that have custom amounts
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

            // Skip if amount is 0 or if it's a flood zone to skip
            if ($customAmount <= 0 || ($floodZoneIdToSkip !== null && $insuranceClauseId === $floodZoneIdToSkip)) {
                continue;
            }

            // Check if this is the earthquake clause and we have a tariff number
            $tariffNumber = $insuranceClause->getTariffNumber();
            if ($earthquakeId !== null
                && $earthquakeTariffNumber !== null
                && $insuranceClauseId === $earthquakeId
            ) {
                // Use the earthquake zone tariff number instead
                $tariffNumber = $earthquakeTariffNumber;
            }

            // Calculate line total
            $lineTotal = $insuranceClause->getHasTariffNumber()
                ? $customAmount * $tariffNumber / 100.0
                : $insuranceClause->getTariffAmount();

            $totalPremium += $lineTotal;
        }

        // Calculate discounted premium
        $discountedPremium = $totalPremium * (1 - $discountPercent / 100);

        // Calculate tax
        $taxAmount = $discountedPremium * ($taxPercent / 100);

        // Calculate total amount
        $totalAmount = $discountedPremium + $taxAmount;

        // Return statistics
        return [
            'discount_percent' => $discountPercent,
            'tax_percent' => $taxPercent,
            'statistics' => [
                'total_premium' => number_format($totalPremium, 2, '.', ''),
                'discounted_premium' => number_format($discountedPremium, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', '')
            ]
        ];
    }
}
