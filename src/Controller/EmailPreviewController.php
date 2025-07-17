<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InsurancePolicy;
use App\Repository\InsurancePolicyRepository;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailPreviewController extends AbstractController
{
    private EmailService $emailService;
    private InsurancePolicyRepository $policyRepository;
    private UriSigner $uriSigner;
    private RequestStack $requestStack;

    public function __construct(
        EmailService $emailService,
        InsurancePolicyRepository $policyRepository,
        UriSigner $uriSigner,
        RequestStack $requestStack
    ) {
        $this->emailService = $emailService;
        $this->policyRepository = $policyRepository;
        $this->uriSigner = $uriSigner;
        $this->requestStack = $requestStack;
    }

    /**
     * Generate a signed URL for email preview
     */
    #[Route('/email-preview/generate-link/{id}/{type}', name: 'app_email_preview_generate_link', defaults: ['type' => 'client'])]
    public function generateSignedLink(int $id, string $type): Response
    {
        // Find the insurance policy by ID
        $policy = $this->policyRepository->find($id);

        // If policy not found, return a 404 response
        if (!$policy) {
            throw new NotFoundHttpException('Insurance policy not found');
        }

        // Validate type
        if (!in_array($type, ['client', 'admin'])) {
            throw new NotFoundHttpException('Invalid email type');
        }

        // Generate a signed URL that expires after 7 days
        $signedUrl = $this->generateUrl(
            'app_email_preview_view',
            ['id' => $id, 'type' => $type],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Create a signed version of the URL
        $signedUrl = $this->uriSigner->sign($signedUrl);

        return $this->json([
            'signed_url' => $signedUrl,
            'policy_id' => $id,
            'type' => $type,
            'expires' => 'in 7 days'
        ]);
    }

    /**
     * View email preview using a signed URL
     */
    #[Route('/email-preview/view/{id}/{type}', name: 'app_email_preview_view', defaults: ['type' => 'client'])]
    public function viewEmailPreview(int $id, string $type): Response
    {
        // Check if the URL signature is valid
        $request = $this->requestStack->getCurrentRequest();
        if (!$this->uriSigner->check($request->getUri())) {
            throw new NotFoundHttpException('Invalid signature');
        }

        // Find the insurance policy by ID
        $policy = $this->policyRepository->find($id);

        // If policy not found, return a 404 response
        if (!$policy) {
            throw new NotFoundHttpException('Insurance policy not found');
        }

        // Determine if this is an admin email
        $isAdminEmail = ($type === 'admin');

        // Generate the email content
        $emailContent = $this->emailService->generateEmailContent($policy, [], $isAdminEmail);

        // Add a header to indicate this is a preview
        $previewHeader = '
        <div style="position: fixed; top: 0; left: 0; right: 0; background-color: #f8d7da; color: #721c24;
                    padding: 10px; text-align: center; font-family: Arial, sans-serif; z-index: 1000;
                    border-bottom: 1px solid #f5c6cb;">
            This is a preview of the ' . ($isAdminEmail ? 'admin' : 'client') . ' email for policy #' . $policy->getCode() . '
        </div>';

        // Add some padding to the body to account for the header
        $emailContent = str_replace('<body>', '<body style="padding-top: 40px;">' . $previewHeader, $emailContent);

        // Return the content as a direct HTML response
        return new Response($emailContent, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }

    /**
     * List available policies for generating preview links
     */
    #[Route('/email-preview', name: 'app_email_preview_list')]
    public function listPolicies(): Response
    {
        // Get the most recent policies (limit to 20)
        $policies = $this->policyRepository->findBy([], ['id' => 'DESC'], 20);

        // Build a simple HTML page with links to generate signed URLs for each policy
        $html = '
        <!DOCTYPE html>
        <html lang="bg">
        <head>
            <title>Email Preview - Policy List</title>
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
                .button {
                    display: inline-block;
                    padding: 5px 10px;
                    margin-right: 10px;
                    background-color: #8b2131;
                    color: white;
                    border-radius: 3px;
                    font-size: 12px;
                    text-decoration: none;
                    cursor: pointer;
                }
                .button.dark { background-color: #333; }
                .result-area {
                    margin-top: 10px;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                    display: none;
                }
                .signed-url {
                    word-break: break-all;
                    font-family: monospace;
                    padding: 5px;
                    background-color: #e9ecef;
                    border-radius: 3px;
                }
            </style>
            <script>
                function generateSignedLink(policyId, type) {
                    fetch(`/email-preview/generate-link/${policyId}/${type}`)
                        .then(response => response.json())
                        .then(data => {
                            const resultArea = document.getElementById(`result-${policyId}`);
                            resultArea.style.display = "block";
                            resultArea.innerHTML = `
                                <p>Signed URL generated for ${type} email (expires ${data.expires}):</p>
                                <div class="signed-url">${data.signed_url}</div>
                                <p><a href="${data.signed_url}" target="_blank" class="button">Open Preview</a></p>
                            `;
                        })
                        .catch(error => {
                            console.error("Error generating signed link:", error);
                            alert("Error generating signed link. See console for details.");
                        });
                }
            </script>
        </head>
        <body>
            <h1>Email Preview - Generate Signed Links</h1>
            <p>Select a policy to generate a signed preview link:</p>
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
                            <button onclick="generateSignedLink(' . $policy->getId() . ', \'client\')" class="button">
                                Generate Client Link
                            </button>
                            <button onclick="generateSignedLink(' . $policy->getId() . ', \'admin\')" class="button dark">
                                Generate Admin Link
                            </button>
                        </div>
                    </div>
                    <div class="info">
                        <strong>ID:</strong> ' . $policy->getId() . '<br>
                        <strong>Name:</strong> ' . $policy->getFullName() . '<br>
                        <strong>Email:</strong> ' . $policy->getEmail() . '<br>
                        <strong>Created:</strong> ' . $policy->getCreatedAt()->format('Y-m-d H:i:s') . '
                    </div>
                    <div id="result-' . $policy->getId() . '" class="result-area"></div>
                </div>';
            }
        }

        $html .= '
            </div>
        </body>
        </html>';

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }
}
