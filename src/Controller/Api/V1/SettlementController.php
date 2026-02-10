<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/settlements', name: 'api_v1_settlements_')]
class SettlementController extends AbstractController
{
    private SettlementRepository $settlementRepository;

    public function __construct(SettlementRepository $settlementRepository)
    {
        $this->settlementRepository = $settlementRepository;
    }

    #[Route('', name: 'autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->query->get('query', '');
        $limit = $request->query->getInt('limit', 10);

        $settlements = $this->settlementRepository->findByNameOrPostalCode($query, $limit);

        $result = [];
        foreach ($settlements as $settlement) {
            $result[] = [
                'id' => $settlement->getId(),
                'name' => $settlement->getFullName(),
                'post_code' => $settlement->getPostCode(),
            ];
        }

        return $this->json($result);
    }
}
