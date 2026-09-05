<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\StatisticsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StatisticsService is a pure function with no dependencies.
 *
 * Naming note, because it is genuinely confusing: `regularDiscountAmount` and
 * `promoDiscountAmount` are NOT discount amounts. They are the premium remaining
 * AFTER the respective discount. The PDF and email templates label them
 * "СЛЕД ОТСТЪПКА" / "ПРОМО КОД" (i.e. "after discount"), so the rendered output is
 * consistent with the values - only the variable names are misleading.
 */
#[CoversClass(StatisticsService::class)]
final class StatisticsServiceTest extends TestCase
{
    private StatisticsService $service;

    protected function setUp(): void
    {
        $this->service = new StatisticsService();
    }

    /**
     * @param array<string, float> $expected
     */
    #[DataProvider('calculationCases')]
    public function testCalculate(
        float $premium,
        float $taxPercent,
        float $regularDiscountPercent,
        float $promoDiscountPercent,
        array $expected
    ): void {
        $result = $this->service->calculate(
            $premium,
            $taxPercent,
            $regularDiscountPercent,
            $promoDiscountPercent
        );

        foreach ($expected as $key => $value) {
            self::assertSame($value, $result[$key], sprintf('Key "%s" did not match.', $key));
        }
    }

    /**
     * @return iterable<string, array{float, float, float, float, array<string, float>}>
     */
    public static function calculationCases(): iterable
    {
        yield 'seeded defaults: 40% discount, 2% tax, no promo' => [100.0, 2.0, 40.0, 0.0, [
            'insurancePremiumAmount' => 100.0,
            'regularDiscountAmount' => 60.0,
            'promoDiscountAmount' => 0.0,
            'taxAmount' => 1.2,
            'totalAmount' => 61.2,
            'totalAmountWithoutDiscount' => 102.0,
        ]];

        // The promo is taken off the GROSS premium, not compounded on the already
        // discounted amount: 60 - (100 * 10%) = 50, not 60 * 0.9 = 54.
        yield 'promo is subtracted from the gross premium, not compounded' => [100.0, 2.0, 40.0, 10.0, [
            'regularDiscountAmount' => 60.0,
            'promoDiscountAmount' => 50.0,
            'taxAmount' => 1.0,
            'totalAmount' => 51.0,
        ]];

        yield 'a zero promo never enters the promo branch' => [100.0, 2.0, 40.0, 0.0, [
            'promoDiscountAmount' => 0.0,
            'totalAmount' => 61.2,
        ]];

        yield 'no discount and no tax returns the premium untouched' => [250.0, 0.0, 0.0, 0.0, [
            'regularDiscountAmount' => 250.0,
            'taxAmount' => 0.0,
            'totalAmount' => 250.0,
            'totalAmountWithoutDiscount' => 250.0,
        ]];

        yield 'zero premium yields zeros throughout' => [0.0, 2.0, 40.0, 0.0, [
            'regularDiscountAmount' => 0.0,
            'taxAmount' => 0.0,
            'totalAmount' => 0.0,
            'totalAmountWithoutDiscount' => 0.0,
        ]];

        yield 'a full 100% discount zeroes the total' => [100.0, 2.0, 100.0, 0.0, [
            'regularDiscountAmount' => 0.0,
            'taxAmount' => 0.0,
            'totalAmount' => 0.0,
        ]];

        yield 'a large insured premium stays exact' => [1000000.0, 2.0, 40.0, 0.0, [
            'regularDiscountAmount' => 600000.0,
            'taxAmount' => 12000.0,
            'totalAmount' => 612000.0,
        ]];
    }

    #[Test]
    public function taxIsChargedOnTheDiscountedAmountNotTheGrossPremium(): void
    {
        $result = $this->service->calculate(200.0, 10.0, 50.0);

        // 200 - 50% = 100; tax is 10% of 100, not of 200.
        self::assertSame(100.0, $result['regularDiscountAmount']);
        self::assertSame(10.0, $result['taxAmount']);
        self::assertSame(110.0, $result['totalAmount']);
        self::assertSame(220.0, $result['totalAmountWithoutDiscount']);
    }

    #[Test]
    public function totalIsExactlyTheDiscountedAmountPlusTaxBecauseEachStepIsRounded(): void
    {
        $result = $this->service->calculate(123.456, 7.5, 13.0);

        // Unlike TariffPresetService::calculatePremiumBreakdown, every step here is
        // round()ed, so the printed parts always add up to the printed total.
        self::assertSame(
            round($result['regularDiscountAmount'] + $result['taxAmount'], 2),
            $result['totalAmount']
        );
    }

    /**
     * Characterization test, not an endorsement: nothing clamps the result at zero, so a
     * promo percentage large enough to exceed the post-discount premium produces a
     * negative total. Reported as a finding.
     */
    #[Test]
    public function aPromoLargerThanTheRemainingPremiumProducesANegativeTotal_KNOWN_GAP(): void
    {
        $result = $this->service->calculate(100.0, 2.0, 40.0, 80.0);

        self::assertSame(-20.0, $result['promoDiscountAmount']);
        self::assertSame(-0.4, $result['taxAmount']);
        self::assertSame(-20.4, $result['totalAmount']);
    }
}
