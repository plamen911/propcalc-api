<?php

namespace App\Controller\Api\V1\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for user profile management
 */
#[Route('/api/v1/admin/profile', name: 'api_v1_admin_profile_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserProfileController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Get current user profile
     */
    #[Route('', name: 'get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * Update user profile
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        /** @var User $user */
        $user = $this->getUser();

        // Update user data
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['email'])) {
            // Check if email is already taken by another user
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'message' => 'Email is already taken',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $user->setEmail($data['email']);
        }

        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        // Save changes
        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
