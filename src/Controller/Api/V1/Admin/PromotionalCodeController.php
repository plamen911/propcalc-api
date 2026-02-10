<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

use App\Controller\Trait\ValidatesEntities;
use App\Entity\PromotionalCode;
use App\Repository\PromotionalCodeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin/promotional-codes', name: 'api_v1_admin_promotional_codes_')]
class PromotionalCodeController extends AbstractController
{
    use ValidatesEntities;

    private PromotionalCodeRepository $promotionalCodeRepository;
    private UserRepository $userRepository;
    private ValidatorInterface $validator;

    public function __construct(
        PromotionalCodeRepository $promotionalCodeRepository,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ) {
        $this->promotionalCodeRepository = $promotionalCodeRepository;
        $this->userRepository = $userRepository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $promotionalCodes = $this->promotionalCodeRepository->findAll();

        $data = array_map(fn(PromotionalCode $code) => $code->toArray(), $promotionalCodes);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $promotionalCode = new PromotionalCode();
        $this->updateEntityFromData($promotionalCode, $data);

        // Check if code is unique
        if ($promotionalCode->getCode()) {
            $existingCode = $this->promotionalCodeRepository->findOneBy(['code' => $promotionalCode->getCode()]);
            if ($existingCode) {
                return $this->json(['errors' => ['Code already exists. Please use a different code.']], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($errorResponse = $this->validationErrors($this->validator->validate($promotionalCode))) {
            return $errorResponse;
        }

        $this->promotionalCodeRepository->save($promotionalCode, true);

        return $this->json($promotionalCode->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get($id): JsonResponse
    {
        // Handle the special cases for "new" and "undefined"
        if ($id === 'new' || $id === 'undefined') {
            return $this->json([
                'id' => null,
                'code' => '',
                'description' => '',
                'discountPercentage' => 0,
                'validFrom' => null,
                'validTo' => null,
                'active' => true,
                'usageLimit' => null,
                'usageCount' => 0,
                'isValid' => false,
                'user' => null,
            ]);
        }

        $promotionalCode = $this->promotionalCodeRepository->find((int)$id);

        if (!$promotionalCode) {
            return $this->json(['error' => 'Промоционалният код не е намерен'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($promotionalCode->toArray());
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $promotionalCode = $this->promotionalCodeRepository->find($id);

        if (!$promotionalCode) {
            return $this->json(['error' => 'Промоционалният код не е намерен'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Store the original code before updating
        $originalCode = $promotionalCode->getCode();

        $this->updateEntityFromData($promotionalCode, $data);

        // Check if code is unique (only if code has changed)
        if ($promotionalCode->getCode() && $promotionalCode->getCode() !== $originalCode) {
            $existingCode = $this->promotionalCodeRepository->findOneBy(['code' => $promotionalCode->getCode()]);
            if ($existingCode) {
                return $this->json(['errors' => ['Code already exists. Please use a different code.']], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($errorResponse = $this->validationErrors($this->validator->validate($promotionalCode))) {
            return $errorResponse;
        }

        $this->promotionalCodeRepository->save($promotionalCode, true);

        return $this->json($promotionalCode->toArray());
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $promotionalCode = $this->promotionalCodeRepository->find($id);

        if (!$promotionalCode) {
            return $this->json(['error' => 'Промоционалният код не е намерен'], Response::HTTP_NOT_FOUND);
        }

        $this->promotionalCodeRepository->remove($promotionalCode, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/user/{userId}', name: 'by_user', methods: ['GET'])]
    public function getByUser(int $userId): JsonResponse
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'Потребителят не е намерен'], Response::HTTP_NOT_FOUND);
        }

        $promotionalCodes = $this->promotionalCodeRepository->findBy(['user' => $user]);

        $data = array_map(fn(PromotionalCode $code) => $code->toArray(), $promotionalCodes);

        return $this->json($data);
    }

    private function updateEntityFromData(PromotionalCode $promotionalCode, array $data): void
    {
        // Only set code if it's a new promotional code (no ID yet)
        if (isset($data['code']) && $promotionalCode->getId() === null) {
            $promotionalCode->setCode($data['code']);
        }

        if (isset($data['description'])) {
            $promotionalCode->setDescription($data['description']);
        }

        // Only set discount percentage if it's a new promotional code (no ID yet)
        if ($promotionalCode->getId() === null) {
            if (isset($data['discount_percentage'])) {
                $promotionalCode->setDiscountPercentage((float)$data['discount_percentage']);
            } elseif (isset($data['discountPercentage'])) {
                $promotionalCode->setDiscountPercentage((float)$data['discountPercentage']);
            }
        }

        if (isset($data['valid_from'])) {
            $validFrom = $data['valid_from'] ? new \DateTime($data['valid_from']) : null;
            $promotionalCode->setValidFrom($validFrom);
        } elseif (isset($data['validFrom'])) {
            $validFrom = $data['validFrom'] ? new \DateTime($data['validFrom']) : null;
            $promotionalCode->setValidFrom($validFrom);
        }

        if (isset($data['valid_to'])) {
            $validTo = $data['valid_to'] ? new \DateTime($data['valid_to']) : null;
            $promotionalCode->setValidTo($validTo);
        } elseif (isset($data['validTo'])) {
            $validTo = $data['validTo'] ? new \DateTime($data['validTo']) : null;
            $promotionalCode->setValidTo($validTo);
        }

        if (isset($data['active'])) {
            $promotionalCode->setActive($data['active']);
        }

        if (isset($data['usage_limit'])) {
            $promotionalCode->setUsageLimit($data['usage_limit']);
        } elseif (isset($data['usageLimit'])) {
            $promotionalCode->setUsageLimit($data['usageLimit']);
        }

        if (isset($data['usage_count'])) {
            $promotionalCode->setUsageCount($data['usage_count']);
        } elseif (isset($data['usageCount'])) {
            $promotionalCode->setUsageCount($data['usageCount']);
        }

        if (isset($data['user_id'])) {
            if ($data['user_id'] === null) {
                $promotionalCode->setUser(null);
            } else {
                $user = $this->userRepository->find($data['user_id']);
                if ($user) {
                    $promotionalCode->setUser($user);
                }
            }
        } elseif (isset($data['user'])) {
            if ($data['user'] === null) {
                $promotionalCode->setUser(null);
            } elseif (isset($data['user']['id'])) {
                $user = $this->userRepository->find($data['user']['id']);
                if ($user) {
                    $promotionalCode->setUser($user);
                }
            }
        }
    }
}
