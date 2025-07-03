<?php

namespace App\Controller\Api\V1;

use App\Service\EmailService;
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
    private EmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        PdfService $pdfService,
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->pdfService = $pdfService;
        $this->emailService = $emailService;
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

    /**
     * Send a PDF document for a tariff via email
     */
    #[Route('/pdf/email', name: 'app_tariff_pdf_email', methods: ['POST'])]
    public function sendPdfViaEmail(Request $request): JsonResponse
    {
        try {
            // Get data from request
            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['selectedTariff']) || !isset($data['recipientEmail'])) {
                return new JsonResponse(['error' => 'Invalid data. Required: selectedTariff and recipientEmail'], Response::HTTP_BAD_REQUEST);
            }

            // Validate email
            if (!filter_var($data['recipientEmail'], FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Invalid email address'], Response::HTTP_BAD_REQUEST);
            }

            // Generate PDF
            $pdfContent = $this->pdfService->generateTariffPdf($data);

            // Generate filename
            $filename = 'tariff-' . $data['selectedTariff']['name'] . '.pdf';

            // Send PDF via email
            $emailSent = $this->emailService->sendPdfViaEmail(
                $data['recipientEmail'],
                $pdfContent,
                $filename,
                'Информация за тарифа - ' . $data['selectedTariff']['name']
            );

            if ($emailSent) {
                return new JsonResponse(['success' => true, 'message' => 'PDF sent successfully']);
            } else {
                return new JsonResponse(['error' => 'Failed to send email'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to send tariff PDF via email: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Failed to send PDF via email'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
