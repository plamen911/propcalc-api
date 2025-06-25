<?php

namespace App\Controller;

use App\Entity\InsurancePolicy;
use App\Repository\InsurancePolicyRepository;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;

class EmailTestController extends AbstractController
{
    private EmailService $emailService;
    private InsurancePolicyRepository $policyRepository;

    public function __construct(
        EmailService $emailService,
        InsurancePolicyRepository $policyRepository
    ) {
        $this->emailService = $emailService;
        $this->policyRepository = $policyRepository;
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/email-test/{id}/submit', name: 'app_email_test_submit')]
    public function testSubmitEmail(int $id): Response
    {
        // Find the insurance policy by ID
        $policy = $this->policyRepository->find($id);

        // If policy not found, return a 404 response
        if (!$policy) {
            return new Response('Insurance policy not found', Response::HTTP_NOT_FOUND);
        }

        $result = $this->emailService->sendOrderConfirmationEmails($policy);

        if ($result) {
            return new Response('Successfully sent emails', Response::HTTP_CREATED);
        }

        return new Response('Failed to send emails', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/email-test', name: 'app_email_test_list')]
    public function listPolicies(): Response
    {
        // Get the most recent policies (limit to 20)
        $policies = $this->policyRepository->findBy([], ['id' => 'DESC'], 20);

        // Build a simple HTML page with links to test each policy
        $html = '
        <!DOCTYPE html>
        <html lang="bg">
        <head>
            <title>Email Test - Policy List</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #8b2131; }
                .policy-list { margin-top: 20px; }
                .policy-item { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
                .policy-item:hover { background-color: #f5f5f5; }
                a { color: #8b2131; text-decoration: none; }
                a:hover { text-decoration: underline; }
                .code { font-weight: bold; }
                .info { color: #666; font-size: 0.9em; margin-top: 5px; }
            </style>
        </head>
        <body>
            <h1>Email Test - Policy List</h1>
            <p>Select a policy to view its email template:</p>
            <div class="policy-list">';

        if (empty($policies)) {
            $html .= '<p>No policies found.</p>';
        } else {
            foreach ($policies as $policy) {
                $html .= '
                <div class="policy-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="code">' . $policy->getCode() . '</span>
                        <div>
                            <a href="' . $this->generateUrl('app_email_test_view', ['id' => $policy->getId(), 'type' => 'client']) . '"
                               style="margin-right: 10px; padding: 5px 10px; background-color: #8b2131; color: white; border-radius: 3px; font-size: 12px; text-decoration: none;">
                               Client View
                            </a>
                            <a href="' . $this->generateUrl('app_email_test_view', ['id' => $policy->getId(), 'type' => 'admin']) . '"
                               style="padding: 5px 10px; background-color: #333; color: white; border-radius: 3px; font-size: 12px; text-decoration: none;">
                               Admin View
                            </a>
                        </div>
                    </div>
                    <div class="info">
                        <strong>ID:</strong> ' . $policy->getId() . '<br>
                        <strong>Name:</strong> ' . $policy->getFullName() . '<br>
                        <strong>Email:</strong> ' . $policy->getEmail() . '<br>
                        <strong>Created:</strong> ' . $policy->getCreatedAt()->format('Y-m-d H:i:s') . '
                    </div>
                </div>';
            }
        }

        $html .= '
            </div>
        </body>
        </html>';

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }

    #[Route('/email-test/{id}/{type}', name: 'app_email_test_view', defaults: ['type' => 'client'])]
    public function testEmail(int $id, string $type): Response
    {
        // Find the insurance policy by ID
        $policy = $this->policyRepository->find($id);

        // If policy not found, return a 404 response
        if (!$policy) {
            return new Response('Insurance policy not found', Response::HTTP_NOT_FOUND);
        }

        // Determine if this is an admin email
        $isAdminEmail = ($type === 'admin');

        // Generate the email content
        $emailContent = $this->emailService->generateEmailContent($policy, [], $isAdminEmail);

        // Add navigation buttons at the top of the email content
        $oppositeType = $isAdminEmail ? 'client' : 'admin';
        $oppositeTypeLabel = $isAdminEmail ? 'Client View' : 'Admin View';
        $currentTypeLabel = $isAdminEmail ? 'Admin View' : 'Client View';

        $navigationButtons = '
        <div style="position: fixed; top: 10px; right: 10px; z-index: 1000; display: flex; gap: 10px;">
            <a href="' . $this->generateUrl('app_email_test_list') . '" style="
                display: inline-block;
                padding: 8px 16px;
                background-color: #8b2131;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-family: Arial, sans-serif;
                font-size: 14px;
                font-weight: bold;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            ">Back to List</a>
            <a href="' . $this->generateUrl('app_email_test_view', ['id' => $id, 'type' => $oppositeType]) . '" style="
                display: inline-block;
                padding: 8px 16px;
                background-color: ' . ($isAdminEmail ? '#8b2131' : '#333') . ';
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-family: Arial, sans-serif;
                font-size: 14px;
                font-weight: bold;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            ">' . $oppositeTypeLabel . '</a>
            <span style="
                display: inline-block;
                padding: 8px 16px;
                background-color: #4CAF50;
                color: white;
                border-radius: 4px;
                font-family: Arial, sans-serif;
                font-size: 14px;
                font-weight: bold;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            ">Current: ' . $currentTypeLabel . '</span>
        </div>';

        // Insert the navigation buttons after the <body> tag
        $emailContent = str_replace('<body>', '<body>' . $navigationButtons, $emailContent);

        // Return the content as a direct HTML response
        return new Response($emailContent, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
