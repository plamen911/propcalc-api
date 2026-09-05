<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Service\PdfService;
use App\Service\TariffPresetService;
use App\Tests\Support\NoNetworkStreamWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PdfService renders with dompdf configured with isRemoteEnabled, and its HTML embeds
 * a hardcoded remote logo. NoNetworkStreamWrapper (installed in tests/bootstrap.php)
 * intercepts that fetch, so a PDF is produced without opening a socket.
 */
#[CoversClass(PdfService::class)]
final class PdfServiceTest extends KernelTestCase
{
    private PdfService $pdfService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->pdfService = self::getContainer()->get(PdfService::class);
        NoNetworkStreamWrapper::reset();
    }

    /**
     * @return array<string, mixed>
     */
    private function tariffData(array $overrides = []): array
    {
        $preset = self::getContainer()->get(TariffPresetService::class)->getTariffPresets()[0];

        return array_merge([
            'selectedTariff' => $preset,
            'promoCodeValid' => false,
            'promoDiscount' => 0,
            'estateData' => [
                'area_sq_meters' => 85,
                'settlement_id' => 1,
                'estate_type_id' => 1,
                'distance_to_water_id' => 2,
            ],
        ], $overrides);
    }

    #[Test]
    public function itProducesARealPdfDocument(): void
    {
        $pdf = $this->pdfService->generateTariffPdf($this->tariffData());

        self::assertStringStartsWith('%PDF-', $pdf, 'Output should carry the PDF magic bytes.');
        self::assertStringContainsString('%%EOF', $pdf);
        self::assertGreaterThan(2000, strlen($pdf), 'A one-page tariff PDF should not be trivially small.');
    }

    #[Test]
    public function renderingNeverOpensASocket(): void
    {
        $this->pdfService->generateTariffPdf($this->tariffData());

        // The only outbound attempt is the hardcoded logo, and it was served locally.
        foreach (NoNetworkStreamWrapper::$attempts as $attempt) {
            self::assertStringContainsString('daike.eu/c/assets/logo.jpg', $attempt);
        }
    }

    #[Test]
    public function aMissingSelectedTariffIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->pdfService->generateTariffPdf(['estateData' => []]);
    }

    #[Test]
    public function aPromoCodeChangesTheRenderedDocument(): void
    {
        $withoutPromo = $this->pdfService->generateTariffPdf($this->tariffData());
        $withPromo = $this->pdfService->generateTariffPdf(
            $this->tariffData(['promoCodeValid' => true, 'promoDiscount' => 10])
        );

        self::assertNotSame(strlen($withoutPromo), strlen($withPromo));
    }

    #[Test]
    public function itRendersForEverySeededPreset(): void
    {
        foreach (self::getContainer()->get(TariffPresetService::class)->getTariffPresets() as $preset) {
            $pdf = $this->pdfService->generateTariffPdf($this->tariffData(['selectedTariff' => $preset]));

            self::assertStringStartsWith('%PDF-', $pdf, sprintf('Preset "%s" failed to render.', $preset['name']));
        }
    }
}
