<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

use App\Controller\Trait\ValidatesEntities;
use App\Entity\InsuranceClause;
use App\Repository\InsuranceClauseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/insurance-policies/admin/insurance-clauses', name: 'api_v1_insurance_policies_admin_insurance_clauses_')]
class InsuranceClauseController extends AbstractController
{
    use ValidatesEntities;

    private InsuranceClauseRepository $insuranceClauseRepository;
    private ValidatorInterface $validator;

    public function __construct(
        InsuranceClauseRepository $insuranceClauseRepository,
        ValidatorInterface $validator
    ) {
        $this->insuranceClauseRepository = $insuranceClauseRepository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $insuranceClauses = $this->insuranceClauseRepository->findAll();

        $data = [];
        foreach ($insuranceClauses as $clause) {
            $data[] = [
                'id' => $clause->getId(),
                'name' => $clause->getName(),
                'description' => $clause->getDescription(),
                'tariff_number' => $clause->getTariffNumber(),
                'has_tariff_number' => $clause->getHasTariffNumber(),
                'tariff_amount' => $clause->getTariffAmount(),
                'position' => $clause->getPosition(),
                'active' => $clause->isActive(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $insuranceClause = $this->insuranceClauseRepository->find($id);

        if (!$insuranceClause) {
            return $this->json(['error' => 'Insurance clause not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $insuranceClause->setName($data['name']);
        }

        if (isset($data['description'])) {
            $insuranceClause->setDescription($data['description']);
        }

        if (isset($data['tariff_number'])) {
            $insuranceClause->setTariffNumber($data['tariff_number']);
        }

        if (isset($data['has_tariff_number'])) {
            $insuranceClause->setHasTariffNumber($data['has_tariff_number']);
        }

        if (isset($data['tariff_amount'])) {
            $insuranceClause->setTariffAmount($data['tariff_amount']);
        }

        if (isset($data['position'])) {
            $insuranceClause->setPosition($data['position']);
        }

        if (isset($data['active'])) {
            // Ensure that insurance clause with ID = 1 is always active
            if ($id === 1) {
                $insuranceClause->setActive(true);
            } else {
                $insuranceClause->setActive($data['active']);
            }
        }

        if ($errorResponse = $this->validationErrors($this->validator->validate($insuranceClause))) {
            return $errorResponse;
        }

        $this->insuranceClauseRepository->save($insuranceClause, true);

        return $this->json([
            'id' => $insuranceClause->getId(),
            'name' => $insuranceClause->getName(),
            'description' => $insuranceClause->getDescription(),
            'tariff_number' => $insuranceClause->getTariffNumber(),
            'has_tariff_number' => $insuranceClause->getHasTariffNumber(),
            'tariff_amount' => $insuranceClause->getTariffAmount(),
            'position' => $insuranceClause->getPosition(),
            'active' => $insuranceClause->isActive(),
        ]);
    }
}
