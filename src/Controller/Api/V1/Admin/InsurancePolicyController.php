<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

use App\Entity\InsurancePolicy;
use App\Repository\InsurancePolicyRepository;
use App\Repository\SettlementRepository;
use App\Repository\EstateTypeRepository;
use App\Repository\PersonRoleRepository;
use App\Repository\IdNumberTypeRepository;
use App\Repository\TariffPresetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/insurance-policies/admin', name: 'api_v1_insurance_policies_admin_')]
class InsurancePolicyController extends AbstractController
{
    private InsurancePolicyRepository $insurancePolicyRepository;
    private SettlementRepository $settlementRepository;
    private EstateTypeRepository $estateTypeRepository;
    private PersonRoleRepository $personRoleRepository;
    private IdNumberTypeRepository $idNumberTypeRepository;
    private TariffPresetRepository $tariffPresetRepository;

    public function __construct(
        InsurancePolicyRepository $insurancePolicyRepository,
        SettlementRepository $settlementRepository,
        EstateTypeRepository $estateTypeRepository,
        PersonRoleRepository $personRoleRepository,
        IdNumberTypeRepository $idNumberTypeRepository,
        TariffPresetRepository $tariffPresetRepository
    ) {
        $this->insurancePolicyRepository = $insurancePolicyRepository;
        $this->settlementRepository = $settlementRepository;
        $this->estateTypeRepository = $estateTypeRepository;
        $this->personRoleRepository = $personRoleRepository;
        $this->idNumberTypeRepository = $idNumberTypeRepository;
        $this->tariffPresetRepository = $tariffPresetRepository;
    }

    #[Route('/policies', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 10;

        // Validate sort parameters
        $allowedSortFields = ['createdAt', 'code', 'fullName', 'total', 'settlement'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'createdAt';
        }
        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) {
            $sortOrder = 'DESC';
        }

        try {
            $result = $this->insurancePolicyRepository->findWithPagination(
                $page,
                $limit,
                $sortBy,
                $sortOrder,
                $search,
                $status
            );

            return $this->json([
                'policies' => $result['policies'],
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $result['totalPages'],
                    'totalItems' => $result['totalItems'],
                    'itemsPerPage' => $limit,
                    'hasNextPage' => $page < $result['totalPages'],
                    'hasPreviousPage' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error fetching policies: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/policies/{id}', name: 'view', methods: ['GET'])]
    public function view(int $id): JsonResponse
    {
        try {
            $policy = $this->insurancePolicyRepository->findWithDetails($id);

            if (!$policy) {
                return $this->json(['error' => 'Insurance policy not found'], Response::HTTP_NOT_FOUND);
            }

            return $this->json($policy);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error fetching policy details: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/policies/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->insurancePolicyRepository->getStats();

            return $this->json($stats);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error fetching statistics: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 