<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AppConfigRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PdfService
{
    private LoggerInterface $logger;
    private AppConfigRepository $appConfigRepository;

    public function __construct(
        LoggerInterface $logger,
        AppConfigRepository $appConfigRepository
    ) {
        $this->logger = $logger;
        $this->appConfigRepository = $appConfigRepository;
    }

    /**
     * Generate a PDF document from tariff data
     *
     * @param array $tariffData The tariff data
     * @return string The PDF content as a binary string
     */
    public function generateTariffPdf(array $tariffData): string
    {
        try {
            // Get currency symbol
            $currencyConfig = $this->appConfigRepository->findOneBy(['name' => 'CURRENCY']);
            $currencySymbol = $currencyConfig ? $currencyConfig->getValue() : '';

            // Generate HTML content
            $content = $this->generateHtmlContent($tariffData, $currencySymbol);

            // Configure Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('defaultMediaType', 'all');
            $options->set('isFontSubsettingEnabled', true);

            // Create Dompdf instance
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($content, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Return PDF as string
            return $dompdf->output();
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate tariff PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate the HTML content for the PDF
     *
     * @param array $tariffData The tariff data
     * @param string $currencySymbol The currency symbol
     * @return string The HTML content
     */
    private function generateHtmlContent(array $tariffData, string $currencySymbol): string
    {
        // Extract data from tariffData
        $selectedTariff = $tariffData['selectedTariff'] ?? null;
        $promoCodeValid = $tariffData['promoCodeValid'] ?? false;
        $promoDiscount = $tariffData['promoDiscount'] ?? 0;

        if (!$selectedTariff) {
            throw new \InvalidArgumentException('Selected tariff data is required');
        }

        // Build the HTML content
        $content = '
        <!DOCTYPE html>
        <html lang="bg">
        <head>
            <meta charset="UTF-8">
            <title>Тарифа - ' . $selectedTariff['name'] . '</title>
            <style>
                body { font-family: "DejaVu Sans", "Arial Unicode MS", Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background-color: #8b2131; color: white; padding: 15px; text-align: center; }
                .section { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; width: 100%; box-sizing: border-box; }
                .section-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #8b2131; }
                .item { margin-bottom: 8px; }
                .label { font-weight: bold; }
                .value { }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                table { width: 100%; border-collapse: collapse; }
                table, th, td { border: 1px solid #ddd; }
                th, td { padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
                .blue-dot { background-color: #3498db; }
                .green-dot { background-color: #2ecc71; }
                .yellow-dot { background-color: #f1c40f; }
                .red-dot { background-color: #e74c3c; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Тарифа - ' . $selectedTariff['name'] . '</h1>
                    <p>Дата: ' . date('d.m.Y H:i:s') . '</p>
                </div>';

        // Tariff information section
        $content .= '
                <div class="section">
                    <div class="section-title">Вие избрахте покритие "' . $selectedTariff['name'] . '" за Вашето имущество</div>';

        // Display clauses if available
        if (isset($selectedTariff['tariff_preset_clauses']) && !empty($selectedTariff['tariff_preset_clauses'])) {
            $content .= '
                    <div class="item" style="margin-top: 15px; background-color: #f9f9f9; padding: 10px; border-radius: 5px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Клаузи</th>
                                    <th style="text-align: right;">Застрахователна сума</th>
                                </tr>
                            </thead>
                            <tbody>';

            foreach ($selectedTariff['tariff_preset_clauses'] as $clause) {
                // Only display clauses with non-zero amounts
                if (isset($clause['tariff_amount']) && floatval($clause['tariff_amount']) > 0) {
                    $content .= '
                                <tr>
                                    <td>' . $clause['insurance_clause']['name'] . '</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($clause['tariff_amount']) . ' ' . $currencySymbol . '</td>
                                </tr>';
                }
            }

            $content .= '
                            </tbody>
                        </table>
                    </div>';
        }

        // Statistics section
        if (isset($selectedTariff['statistics'])) {
            $content .= '
                    <div class="item" style="margin-top: 20px;">
                        <table>
                            <tbody>';

            // Insurance premium
            if (isset($selectedTariff['statistics']['total_premium'])) {
                $insurancePremiumAmount = $this->calculate(
                    $selectedTariff['statistics']['total_premium'],
                    $selectedTariff['tax_percent'] ?? 0,
                    $selectedTariff['discount_percent'] ?? 0,
                    $promoDiscount
                )['insurancePremiumAmount'];

                $content .= '
                                <tr>
                                    <td><span class="dot blue-dot"></span> Застрахователна премия</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($insurancePremiumAmount) . ' ' . $currencySymbol . '</td>
                                </tr>';
            }

            // Discounted premium
            if (isset($selectedTariff['statistics']['discounted_premium']) && isset($selectedTariff['discount_percent']) && $selectedTariff['discount_percent'] > 0) {
                $regularDiscountAmount = $this->calculate(
                    $selectedTariff['statistics']['total_premium'],
                    $selectedTariff['tax_percent'] ?? 0,
                    $selectedTariff['discount_percent'] ?? 0,
                    $promoDiscount
                )['regularDiscountAmount'];

                $content .= '
                                <tr>
                                    <td><span class="dot green-dot"></span> Застрахователна премия след отстъпка ' . $selectedTariff['discount_percent'] . '%</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($regularDiscountAmount) . ' ' . $currencySymbol . '</td>
                                </tr>';
            }

            // Promo code discount
            if ($promoCodeValid && $promoDiscount > 0 && isset($selectedTariff['statistics']['discounted_premium'])) {
                $promoDiscountAmount = $this->calculate(
                    $selectedTariff['statistics']['total_premium'],
                    $selectedTariff['tax_percent'] ?? 0,
                    $selectedTariff['discount_percent'] ?? 0,
                    $promoDiscount
                )['promoDiscountAmount'];

                $content .= '
                                <tr>
                                    <td><span class="dot green-dot"></span> Застрахователна премия след приложен промо код ' . $promoDiscount . '%</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($promoDiscountAmount) . ' ' . $currencySymbol . '</td>
                                </tr>';
            }

            // Tax amount
            if (isset($selectedTariff['statistics']['tax_amount']) && isset($selectedTariff['tax_percent'])) {
                $taxAmount = $this->calculate(
                    $selectedTariff['statistics']['total_premium'],
                    $selectedTariff['tax_percent'] ?? 0,
                    $selectedTariff['discount_percent'] ?? 0,
                    $promoDiscount
                )['taxAmount'];

                $content .= '
                                <tr>
                                    <td><span class="dot yellow-dot"></span> Данък върху застрахователната премия ' . $selectedTariff['tax_percent'] . '%</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($taxAmount) . ' ' . $currencySymbol . '</td>
                                </tr>';
            }

            // Total amount
            $totalAmount = $this->calculate(
                $selectedTariff['statistics']['total_premium'],
                $selectedTariff['tax_percent'] ?? 0,
                $selectedTariff['discount_percent'] ?? 0,
                $promoDiscount
            )['totalAmount'];

            $content .= '
                                <tr>
                                    <td><span class="dot red-dot"></span> Общо дължима сума за една година</td>
                                    <td style="text-align: right; font-weight: bold; color: #8b2131;">' . $this->formatCurrency($totalAmount) . ' ' . $currencySymbol . '</td>
                                </tr>';

            $content .= '
                            </tbody>
                        </table>
                    </div>';
        }

        $content .= '
                </div>';

        // Footer
        $content .= '
                <div class="footer">
                    <p>Това е автоматично генериран документ.</p>
                    <p>&copy; ' . date('Y') . ' ЗБ "Дженерал Брокер Клуб" ООД. Всички права запазени.</p>
                </div>
            </div>
        </body>
        </html>';

        return $content;
    }

    /**
     * Format currency with thousand separators (spaces)
     *
     * @param float|string $value The value to format
     * @param int $fraction The number of decimal places
     * @return string The formatted value
     */
    private function formatCurrency($value, int $fraction = 2): string
    {
        $number = floatval($value);
        if (is_nan($number)) {
            return '0.00';
        }
        return number_format($number, $fraction, '.', ' ');
    }

    /**
     * Calculate insurance premium, discounts, taxes, and total amounts
     *
     * @param float|string $insurancePremiumAmount The insurance premium amount
     * @param float|string $taxPercent The tax percentage
     * @param float|string $regularDiscountPercent The regular discount percentage
     * @param float|string $promoDiscountPercent The promo discount percentage
     * @return array The calculated amounts
     */
    private function calculate($insurancePremiumAmount, $taxPercent, $regularDiscountPercent, $promoDiscountPercent = 0): array
    {
        $insurancePremiumAmount = floatval($insurancePremiumAmount);
        $taxPercent = floatval($taxPercent);
        $regularDiscountPercent = floatval($regularDiscountPercent);
        $promoDiscountPercent = floatval($promoDiscountPercent);

        $regularDiscountAmount = round($insurancePremiumAmount - ($insurancePremiumAmount * ($regularDiscountPercent / 100)), 2);
        $taxAmount = round($regularDiscountAmount * $taxPercent / 100, 2);
        $totalAmount = round($regularDiscountAmount + $taxAmount, 2);
        $promoDiscountAmount = 0.0;

        if ($promoDiscountPercent > 0) {
            $promoDiscountAmount = round($regularDiscountAmount - ($insurancePremiumAmount * ($promoDiscountPercent / 100)), 2);
            $taxAmount = round($promoDiscountAmount * $taxPercent / 100, 2);
            $totalAmount = round($promoDiscountAmount + $taxAmount, 2);
        }

        $totalAmountWithoutDiscount = round($insurancePremiumAmount + ($insurancePremiumAmount * $taxPercent / 100), 2);

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
