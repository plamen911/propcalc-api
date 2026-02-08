<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AppConfigRepository;
use App\Repository\EstateTypeRepository;
use App\Repository\SettlementRepository;
use App\Repository\WaterDistanceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\DBAL\Connection;

class PdfService
{
    private LoggerInterface $logger;
    private AppConfigRepository $appConfigRepository;
    private SettlementRepository $settlementRepository;
    private EstateTypeRepository $estateTypeRepository;
    private WaterDistanceRepository $waterDistanceRepository;
    private StatisticsService $statisticsService;

    public function __construct(
        LoggerInterface $logger,
        AppConfigRepository $appConfigRepository,
        SettlementRepository $settlementRepository,
        EstateTypeRepository $estateTypeRepository,
        WaterDistanceRepository $waterDistanceRepository,
        StatisticsService $statisticsService
    ) {
        $this->logger = $logger;
        $this->appConfigRepository = $appConfigRepository;
        $this->settlementRepository = $settlementRepository;
        $this->estateTypeRepository = $estateTypeRepository;
        $this->waterDistanceRepository = $waterDistanceRepository;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Generate a PDF document from tariff data
     *
     * @param array $tariffData The tariff data
     * @return string The PDF content as a binary string
     * @throws Exception
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

            // Create a Dompdf instance
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($content, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Return PDF as string
            return $dompdf->output();
        } catch (Exception $e) {
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
                    line-height: 1.3;
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
                    padding: 5px;
                    box-sizing: border-box;
                    overflow-x: hidden;
                }

                /* Header styles - optimized for printing */
                .header {
                    background-color: #8b2131;
                    color: white;
                    padding: 6px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                    margin-bottom: 8px;
                    width: 100%;
                    box-sizing: border-box;
                    overflow-x: hidden;
                }
                .header h1 {
                    margin: 0 0 2px 0;
                    font-size: 16px;
                    letter-spacing: 0.5px;
                }
                .header p {
                    margin: 0;
                    font-size: 10px;
                    opacity: 0.9;
                }

                /* Section styles - optimized for printing */
                .section {
                    margin-bottom: 8px;
                    border: 1px solid #cccccc;
                    padding: 6px;
                    width: 100%;
                    box-sizing: border-box;
                    border-radius: 5px;
                    background-color: #fafafa;
                    overflow-x: hidden;
                }
                .section-title {
                    font-size: 13px;
                    font-weight: bold;
                    margin-bottom: 5px;
                    color: #8b2131;
                    padding-bottom: 3px;
                    border-bottom: 1px solid #dddddd;
                }
                .item {
                    margin-bottom: 4px;
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
                    margin-bottom: 4px;
                    box-sizing: border-box;
                    table-layout: fixed;
                }
                table, th, td {
                    border: 1px solid #aaaaaa;
                }
                th, td {
                    padding: 3px 4px;
                    text-align: left;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    font-size: 10px;
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
                    font-size: 11px;
                }

                /* Footer styles - optimized for printing */
                .footer {
                    text-align: center;
                    margin-top: 10px;
                    padding-top: 5px;
                    font-size: 9px;
                    color: #555;
                    border-top: 1px solid #cccccc;
                }
                .footer p {
                    margin: 1px 0;
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

        $stats = $this->statisticsService->calculate(
            insurancePremiumAmount: (float) $selectedTariff['statistics']['total_premium'],
            taxPercent: (float) $selectedTariff['tax_percent'] ?? 0,
            regularDiscountPercent: (float) $selectedTariff['discount_percent'] ?? 0,
            promoDiscountPercent: (float) $promoDiscount
        );

        // Statistics section
        if (isset($selectedTariff['statistics'])) {
            // Insurance premium
            if (isset($selectedTariff['statistics']['total_premium'])) {
                $insurancePremiumAmount = $stats['insurancePremiumAmount'];

                $content .= '
                                <tr>
                                    <th>БАЗОВА СУМА</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($insurancePremiumAmount) . ' ' . $currencySymbol . '</th>
                                </tr>';
            }

            // Discounted premium
            if (isset($selectedTariff['statistics']['discounted_premium']) && isset($selectedTariff['discount_percent']) && $selectedTariff['discount_percent'] > 0) {
                $regularDiscountAmount = $stats['regularDiscountAmount'];

                $content .= '
                                <tr>
                                    <th>СЛЕД ОТСТЪПКА -' . $selectedTariff['discount_percent'] . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($regularDiscountAmount) . ' ' . $currencySymbol . '</th>
                                </tr>';
            }

            // Promo code discount
            if ($promoCodeValid && $promoDiscount > 0 && isset($selectedTariff['statistics']['discounted_premium'])) {
                $promoDiscountAmount = $stats['promoDiscountAmount'];

                $content .= '
                                <tr>
                                    <th>ПРОМО КОД -' . $promoDiscount . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($promoDiscountAmount) . ' ' . $currencySymbol . '</th>
                                </tr>';
            }

            // Tax amount
            if (isset($selectedTariff['statistics']['tax_amount']) && isset($selectedTariff['tax_percent'])) {
                $taxAmount = $stats['taxAmount'];

                $content .= '
                                <tr>
                                    <th>ДЗП +' . $selectedTariff['tax_percent'] . '%</th>
                                    <th class="text-right highlight">' . $this->formatCurrency($taxAmount) . ' ' . $currencySymbol . '</th>
                                </tr>';
            }

            // Total amount
            $totalAmount = $stats['totalAmount'];

            $content .= '
                                <tr>
                                    <th>ДЪЛЖИМА СУМА ЗА ГОДИНА</th>
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
     * Format currency with a thousand separators (spaces)
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
}
