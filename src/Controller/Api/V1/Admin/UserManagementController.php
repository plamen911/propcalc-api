<?php

namespace App\Controller\Api\V1\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for user management by admin
 */
#[Route('/api/v1/admin/user-management', name: 'api_v1_admin_user_management_')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    // Available roles for assignment
    private const AVAILABLE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_OFFICE',
        'ROLE_AGENT'
    ];

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Get a single user by ID
     */
    #[Route('/{id}', name: 'get_user', methods: ['GET'])]
    public function getUserById(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'Потребителят не е намерен'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * Create a new user
     */
    #[Route('', name: 'create_user', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'message' => 'Имейл и парола са задължителни',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if email is already taken
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'message' => 'Имейлът вече е зает',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create new user
        $user = new User();
        $user->setEmail($data['email']);

        // Set password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Set name if provided
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        // Set roles if provided and valid
        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = $this->validateAndFilterRoles($data['roles']);
            $user->setRoles($roles);
        } else {
            // Default role
            $user->setRoles(['ROLE_AGENT']);
        }

        // Save user
        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Потребителят е създаден успешно',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing user
     */
    #[Route('/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Update email if provided
        if (isset($data['email'])) {
            // Check if email is already taken by another user
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'message' => 'Имейлът вече е зает',
                ], Response::HTTP_BAD_REQUEST);
            }

            $user->setEmail($data['email']);
        }

        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        // Update name if provided
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        // Update roles if provided and valid
        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = $this->validateAndFilterRoles($data['roles']);
            $user->setRoles($roles);
        }

        // Save changes
        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Потребителят е обновен успешно',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    /**
     * Delete a user
     */
    #[Route('/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Don't allow deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            return $this->json([
                'message' => 'Не можете да изтриете собствения си акаунт',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->userRepository->remove($user, true);

        return $this->json(['message' => 'Потребителят е изтрит успешно']);
    }

    /**
     * Validate and filter roles to ensure only allowed roles are assigned
     */
    private function validateAndFilterRoles(array $roles): array
    {
        return array_filter($roles, function($role) {
            return in_array($role, self::AVAILABLE_ROLES);
        });
    }
}
