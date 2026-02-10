<?php

namespace App\Controller\Api\V1\Admin;

use App\Entity\User;
use App\Repository\PromotionalCodeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/user-management', name: 'api_v1_admin_user_management_')]
#[IsGranted('ROLE_ADMIN')]
class UserManagementController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private PromotionalCodeRepository $promotionalCodeRepository;

    // Available roles for assignment
    private const AVAILABLE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_OFFICE',
        'ROLE_AGENT'
    ];

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        PromotionalCodeRepository $promotionalCodeRepository
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->promotionalCodeRepository = $promotionalCodeRepository;
    }

    #[Route('/{id}', name: 'get_user', methods: ['GET'])]
    public function getUserById(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'Потребителят не е намерен'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($user->toArray());
    }

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

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = $this->validateAndFilterRoles($data['roles']);
            $user->setRoles($roles);
        } else {
            $user->setRoles(['ROLE_AGENT']);
        }

        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Потребителят е създаден успешно',
            'user' => $user->toArray(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        if ($errorResponse = $this->updateUserFields($user, $data)) {
            return $errorResponse;
        }

        // Update roles if provided and valid
        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = $this->validateAndFilterRoles($data['roles']);
            $user->setRoles($roles);
        }

        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Потребителят е обновен успешно',
            'user' => $user->toArray(),
        ]);
    }

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

        // Delete promotional codes associated with the user
        $promotionalCodes = $this->promotionalCodeRepository->findBy(['user' => $user]);
        foreach ($promotionalCodes as $promotionalCode) {
            $this->promotionalCodeRepository->remove($promotionalCode, false);
        }

        $this->userRepository->remove($user, true);

        return $this->json(['message' => 'Потребителят е изтрит успешно']);
    }

    private function updateUserFields(User $user, array $data): ?JsonResponse
    {
        if (isset($data['email'])) {
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'message' => 'Имейлът вече е зает',
                ], Response::HTTP_BAD_REQUEST);
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        return null;
    }

    private function validateAndFilterRoles(array $roles): array
    {
        return array_filter($roles, function($role) {
            return in_array($role, self::AVAILABLE_ROLES);
        });
    }
}
