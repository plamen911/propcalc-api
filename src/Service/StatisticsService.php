<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for calculating insurance statistics
 */
class StatisticsService
{
    /**
     * Calculate insurance statistics based on premium amount and various discount percentages
     *
     * @param float $insurancePremiumAmount The base insurance premium amount
     * @param float $taxPercent The tax percentage to apply
     * @param float $regularDiscountPercent The regular discount percentage
     * @param float $promoDiscountPercent Optional promotional discount percentage
     *
     * @return array Statistics including premium amounts, discounts, tax and totals
     */
    public function calculate(
        float $insurancePremiumAmount,
        float $taxPercent,
        float $regularDiscountPercent,
        float $promoDiscountPercent = 0.0
    ): array {
        // Calculate regular discount amount
        $regularDiscountAmount = round($insurancePremiumAmount - ($insurancePremiumAmount * ($regularDiscountPercent / 100)), 2);

        // Initialize tax and total amounts
        $taxAmount = round($regularDiscountAmount * $taxPercent / 100, 2);
        $totalAmount = round($regularDiscountAmount + $taxAmount, 2);

        // Initialize promo discount amount
        $promoDiscountAmount = 0.0;

        // Apply promotional discount if applicable
        if ($promoDiscountPercent > 0) {
            $promoDiscountAmount = round($regularDiscountAmount - ($insurancePremiumAmount * ($promoDiscountPercent / 100)), 2);
            $taxAmount = round($promoDiscountAmount * $taxPercent / 100, 2);
            $totalAmount = round($promoDiscountAmount + $taxAmount, 2);
        }

        // Calculate the total amount without any discounts
        $totalAmountWithoutDiscount = round($insurancePremiumAmount + ($insurancePremiumAmount * $taxPercent / 100), 2);

        // Return all calculated values
        return [
            'insurancePremiumAmount' => $insurancePremiumAmount,
            'regularDiscountAmount' => $regularDiscountAmount,
            'promoDiscountAmount' => $promoDiscountAmount,
            'taxAmount' => $taxAmount,
            'totalAmount' => $totalAmount,
            'totalAmountWithoutDiscount' => $totalAmountWithoutDiscount
        ];
    }
}
