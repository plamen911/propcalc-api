<?php

namespace App\Controller\Api\V1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller to test JWT authentication
 */
#[Route('/api/v1/auth-test', name: 'api_v1_auth_test_')]
class AuthTestController extends AbstractController
{
    /**
     * Public endpoint that doesn't require authentication
     */
    #[Route('/public', name: 'public', methods: ['GET'])]
    public function publicEndpoint(): JsonResponse
    {
        return $this->json([
            'message' => 'This is a public endpoint that anyone can access',
            'authenticated' => $this->getUser() !== null,
            'timestamp' => new \DateTime(),
        ]);
    }

    /**
     * Protected endpoint that requires authentication
     */
    #[Route('/protected', name: 'protected', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function protectedEndpoint(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'message' => 'This is a protected endpoint that requires authentication',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'fullName' => $user->getFullName(),
            ],
            'timestamp' => new \DateTime(),
        ]);
    }

    /**
     * Admin endpoint that requires ROLE_ADMIN
     */
    #[Route('/admin', name: 'admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEndpoint(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'message' => 'This is an admin endpoint that requires ROLE_ADMIN',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'fullName' => $user->getFullName(),
            ],
            'timestamp' => new \DateTime(),
        ]);
    }
}
