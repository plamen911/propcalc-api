<?php

namespace App\Controller\Api\V1;

use App\Service\PdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/tariff')]
class TariffPdfController extends AbstractController
{
    private PdfService $pdfService;
    private LoggerInterface $logger;

    public function __construct(
        PdfService $pdfService,
        LoggerInterface $logger
    ) {
        $this->pdfService = $pdfService;
        $this->logger = $logger;
    }

    /**
     * Generate a PDF document for a tariff
     */
    #[Route('/pdf', name: 'app_tariff_pdf', methods: ['POST'])]
    public function generatePdf(Request $request): Response
    {
        try {
            // Get tariff data from request
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['selectedTariff'])) {
                return new JsonResponse(['error' => 'Invalid tariff data'], Response::HTTP_BAD_REQUEST);
            }

            // Generate PDF
            $pdfContent = $this->pdfService->generateTariffPdf($data);

            // Create response with PDF content
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="tariff.pdf"');

            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate tariff PDF: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Failed to generate PDF'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
