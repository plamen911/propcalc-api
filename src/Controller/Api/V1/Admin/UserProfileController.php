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

#[Route('/api/v1/admin', name: 'api_v1_admin_')]
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

    #[Route('/users', name: 'users_list', methods: ['GET'])]
    public function getAllUsers(): JsonResponse
    {
        $users = $this->userRepository->findAllExceptAnonymous();

        $data = array_map(fn(User $user) => $user->toArray(), $users);

        return $this->json($data);
    }

    #[Route('/profile', name: 'profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($user->toArray(includeId: false));
    }

    #[Route('/profile', name: 'profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        /** @var User $user */
        $user = $this->getUser();

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

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

        $this->userRepository->save($user, true);

        return $this->json([
            'message' => 'Профилът е обновен успешно',
            'user' => $user->toArray(includeId: false),
        ]);
    }
}
