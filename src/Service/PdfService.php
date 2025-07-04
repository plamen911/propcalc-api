<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AppConfigRepository;
use App\Repository\EstateTypeRepository;
use App\Repository\SettlementRepository;
use App\Repository\WaterDistanceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\DBAL\Connection;

class PdfService
{
    private LoggerInterface $logger;
    private AppConfigRepository $appConfigRepository;
    private Connection $connection;
    private SettlementRepository $settlementRepository;
    private EstateTypeRepository $estateTypeRepository;
    private WaterDistanceRepository $waterDistanceRepository;

    public function __construct(
        LoggerInterface $logger,
        AppConfigRepository $appConfigRepository,
        Connection $connection,
        SettlementRepository $settlementRepository,
        EstateTypeRepository $estateTypeRepository,
        WaterDistanceRepository $waterDistanceRepository
    ) {
        $this->logger = $logger;
        $this->appConfigRepository = $appConfigRepository;
        $this->connection = $connection;
        $this->settlementRepository = $settlementRepository;
        $this->estateTypeRepository = $estateTypeRepository;
        $this->waterDistanceRepository = $waterDistanceRepository;
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
        $estateData = $tariffData['estateData'] ?? [];

        if (!$selectedTariff) {
            throw new \InvalidArgumentException('Selected tariff data is required');
        }

        // Build the HTML content
        $content = '
        <!DOCTYPE html>
        <html lang="bg" style="width: 100%; max-width: 100%; overflow-x: hidden;">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Тарифа - ' . $selectedTariff['name'] . '</title>
            <style>
                /* Base styles */
                body {
                    font-family: "DejaVu Sans", "Arial Unicode MS", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #fff;
                    width: 100%;
                    max-width: 100%;
                    overflow-x: hidden;
                }
                .container {
                    width: 100%;
                    max-width: 700px;
                    margin: 0 auto;
                    padding: 10px;
                    box-sizing: border-box;
                    overflow-x: hidden;
                }

                /* Header styles - optimized for printing */
                .header {
                    background-color: #8b2131;
                    color: white;
                    padding: 10px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                    margin-bottom: 15px;
                    width: 100%;
                    box-sizing: border-box;
                    overflow-x: hidden;
                }
                .header h1 {
                    margin: 0 0 5px 0;
                    font-size: 20px;
                    letter-spacing: 0.5px;
                }
                .header p {
                    margin: 0;
                    font-size: 12px;
                    opacity: 0.9;
                }

                /* Section styles - optimized for printing */
                .section {
                    margin-bottom: 15px;
                    border: 1px solid #cccccc;
                    padding: 10px;
                    width: 100%;
                    box-sizing: border-box;
                    border-radius: 5px;
                    background-color: #fafafa;
                    overflow-x: hidden;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    margin-bottom: 10px;
                    color: #8b2131;
                    padding-bottom: 5px;
                    border-bottom: 1px solid #dddddd;
                }
                .item {
                    margin-bottom: 10px;
                    width: 100%;
                    box-sizing: border-box;
                    overflow-x: hidden;
                }
                .label {
                    font-weight: bold;
                    display: inline-block;
                    min-width: 200px;
                }
                .value {
                    display: inline-block;
                }

                /* Table styles - optimized for printing */
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 8px;
                    box-sizing: border-box;
                    table-layout: fixed;
                }
                table, th, td {
                    border: 1px solid #aaaaaa;
                }
                th, td {
                    padding: 5px 6px;
                    text-align: left;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    font-size: 11px;
                }
                th {
                    background-color: #f0f0f0;
                    font-weight: bold;
                    color: #333;
                }
                tbody tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                tfoot tr {
                    background-color: #f0f0f0;
                }
                tfoot th {
                    font-weight: bold;
                    font-size: 12px;
                }

                /* Footer styles - optimized for printing */
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 10px;
                    font-size: 10px;
                    color: #555;
                    border-top: 1px solid #cccccc;
                }
                .footer p {
                    margin: 3px 0;
                }

                /* Utility classes */
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .text-bold {
                    font-weight: bold;
                }
                .highlight {
                    color: #8b2131;
                    font-weight: bold;
                }

                /* Dot indicators */
                .dot {
                    display: inline-block;
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    margin-right: 5px;
                }
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
                    <p>Генерирано на: ' . date('d.m.Y H:i:s') . '</p>
                </div>';

        // Tariff information section
        $content .= '
                <div class="section">
                    <div class="section-title">
                        <span class="highlight">✓</span> Вие избрахте покритие "' . $selectedTariff['name'] . '" за Вашето имущество
                    </div>';
        $content .= '
                    <div class="item">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 70%;">Клаузи</th>
                                    <th style="width: 30%;" class="text-right">Застрахователна сума</th>
                                </tr>
                            </thead>
                            <tbody>';
        // Display clauses if available
        if (!empty($selectedTariff['tariff_preset_clauses'])) {
            foreach ($selectedTariff['tariff_preset_clauses'] as $clause) {
                // Only display clauses with non-zero amounts
                if (isset($clause['tariff_amount']) && floatval($clause['tariff_amount']) > 0) {
                    $content .= '
                                <tr>
                                    <td>' . $clause['insurance_clause']['name'] . '</td>
                                    <td class="text-right highlight">' . $this->formatCurrency($clause['tariff_amount']) . ' ' . $currencySymbol . '</td>
                                </tr>';
                }
            }
        }

        $content .= '</tbody>
                    <tfoot>';

        // Statistics section
        if (isset($selectedTariff['statistics'])) {
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
                                    <th>Застрахователна премия</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($insurancePremiumAmount) . ' ' . $currencySymbol . '</th>
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
                                    <th>Застрахователна премия след отстъпка ' . $selectedTariff['discount_percent'] . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($regularDiscountAmount) . ' ' . $currencySymbol . '</th>
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
                                    <th>Застрахователна премия след приложен промо код ' . $promoDiscount . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($promoDiscountAmount) . ' ' . $currencySymbol . '</th>
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
                                    <th>Данък върху застрахователната премия ' . $selectedTariff['tax_percent'] . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($taxAmount) . ' ' . $currencySymbol . '</th>
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
                                    <th>Общо дължима сума за една година</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($totalAmount) . ' ' . $currencySymbol . '</th>
                                </tr>';

        }

        $content .= '
                            </tfoot>
                        </table>
                    </div>';

        $content .= '
                </div>';

        // Estate Data Section
        if (!empty($estateData)) {
            // Get human-readable names for IDs
            $settlementName = '....';
            $estateTypeName = '....';
            $estateSubtypeName = '....';
            $distanceToWaterName = '....';

            if (!empty($estateData['settlement_id'])) {
                $settlement = $this->settlementRepository->find($estateData['settlement_id']);
                if ($settlement) {
                    $settlementName = $settlement->getFullName();
                }
            }

            if (!empty($estateData['estate_type_id'])) {
                $estateType = $this->estateTypeRepository->find($estateData['estate_type_id']);
                if ($estateType) {
                    $estateTypeName = $estateType->getName();
                }
            }

            if (!empty($estateData['estate_subtype_id'])) {
                $estateSubtype = $this->estateTypeRepository->find($estateData['estate_subtype_id']);
                if ($estateSubtype) {
                    $estateSubtypeName = $estateSubtype->getName();
                }
            }

            if (!empty($estateData['distance_to_water_id'])) {
                $distanceToWater = $this->waterDistanceRepository->find($estateData['distance_to_water_id']);
                if ($distanceToWater) {
                    $distanceToWaterName = $distanceToWater->getName();
                }
            }

            $content .= '
                <div class="section">
                    <div class="section-title">
                        Данни за имота
                    </div>
                    <table>
                        <tbody>
                            <tr>
                                <td style="width: 60%;"><strong>Населено място на имота:</strong></td>
                                <td style="width: 40%;">' . $settlementName . '</td>
                            </tr>
                            <tr>
                                <td><strong>Tип имот:</strong></td>
                                <td>' . $estateTypeName . '</td>
                            </tr>
                            <tr>
                                <td><strong>Вид имот:</strong></td>
                                <td>' . $estateSubtypeName . '</td>
                            </tr>
                            <tr>
                                <td><strong>Отстояние от воден басейн:</strong></td>
                                <td>' . $distanceToWaterName . '</td>
                            </tr>
                            <tr>
                                <td><strong>РЗП:</strong></td>
                                <td>' . ($estateData['area_sq_meters'] ? $estateData['area_sq_meters'] . ' кв.м.' : '....') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>';
        }

        // Footer
        $content .= '
                <div class="footer">
                    <p>Това е автоматично генериран документ и не изисква подпис.</p>
                    <p class="text-center">&copy; ' . date('Y') . ' ЗБ "Дженерал Брокер Клуб" ООД. Всички права запазени.</p>
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
