<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\PromotionalCode;
use App\Repository\PromotionalCodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/promotional-codes', name: 'api_v1_promotional_codes_')]
class PromotionalCodeController extends AbstractController
{
    private PromotionalCodeRepository $promotionalCodeRepository;

    public function __construct(
        PromotionalCodeRepository $promotionalCodeRepository
    ) {
        $this->promotionalCodeRepository = $promotionalCodeRepository;
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['code']) || empty($data['code'])) {
            return $this->json([
                'valid' => false,
                'message' => 'Промоционалният код е задължителен'
            ], Response::HTTP_BAD_REQUEST);
        }

        $code = $data['code'];
        $promotionalCode = $this->promotionalCodeRepository->findOneBy(['code' => $code]);

        if (!$promotionalCode) {
            return $this->json([
                'valid' => false,
                'message' => 'Невалиден промоционален код'
            ]);
        }

        if (!$promotionalCode->isValid()) {
            return $this->json([
                'valid' => false,
                'message' => 'Този промоционален код вече не е валиден'
            ]);
        }

        return $this->json([
            'valid' => true,
            'id' => $promotionalCode->getId(),
            'discountPercentage' => $promotionalCode->getDiscountPercentage(),
            'message' => 'Промоционалният код е валиден'
        ]);
    }
}
