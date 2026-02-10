<?php

namespace App\Controller\Api\V1\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for admin authentication
 */
#[Route('/api/v1/admin/auth', name: 'api_v1_admin_auth_')]
class AdminAuthController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
    }

    /**
     * Admin login endpoint
     * Authenticates an admin user and returns a JWT token
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json([
                'message' => 'Потребителско име и парола са задължителни',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $username = $data['username'];
        $password = $data['password'];

        // Find user by email (username)
        $user = $this->userRepository->findOneBy(['email' => $username]);

        if (!$user) {
            return $this->json([
                'message' => 'Невалидни данни за вход',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Check if user has ROLE_ADMIN
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            return $this->json([
                'message' => 'Достъпът е отказан. Изисква се администраторска роля.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'message' => 'Невалидни данни за вход',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Generate a JWT token for the user
        $token = $this->jwtManager->create($user);

        // Return the token
        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'fullName' => $user->getFullName(),
            ],
        ]);
    }
}
