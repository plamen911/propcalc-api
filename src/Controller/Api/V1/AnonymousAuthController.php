<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Controller for anonymous authentication
 */
#[Route('/api/v1/auth', name: 'api_v1_auth_')]
class AnonymousAuthController extends AbstractController
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
     * Anonymous login endpoint
     * Creates a temporary anonymous user and returns a JWT token
     */
    #[Route('/anonymous', name: 'anonymous', methods: ['POST'])]
    public function anonymousLogin(): JsonResponse
    {
        // Generate a unique email for the anonymous user
        $uuid = Uuid::v4();
        $email = 'anonymous_' . $uuid->toRfc4122() . '@zastrahovaite.com';

        // Create a new anonymous user
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ANONYMOUS']);

        // Generate a random password
        $randomPassword = bin2hex(random_bytes(16));
        $hashedPassword = $this->passwordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hashedPassword);

        // Set optional fields
        $user->setFirstName('Anonymous');
        $user->setLastName('User');

        // Save the user
        $this->userRepository->save($user, true);

        // Generate a JWT token for the user
        $token = $this->jwtManager->create($user);

        // Return the token
        return $this->json([
            'token' => $token,
            'user' => [
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
