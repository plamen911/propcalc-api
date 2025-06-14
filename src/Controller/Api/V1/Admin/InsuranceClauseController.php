<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

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
                'tariff_number' => $clause->getTariffNumber(),
                'has_tariff_number' => $clause->getHasTariffNumber(),
                'tariff_amount' => $clause->getTariffAmount(),
                'position' => $clause->getPosition(),
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

        $errors = $this->validator->validate($insuranceClause);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->insuranceClauseRepository->save($insuranceClause, true);

        return $this->json([
            'id' => $insuranceClause->getId(),
            'name' => $insuranceClause->getName(),
            'tariff_number' => $insuranceClause->getTariffNumber(),
            'has_tariff_number' => $insuranceClause->getHasTariffNumber(),
            'tariff_amount' => $insuranceClause->getTariffAmount(),
            'position' => $insuranceClause->getPosition(),
        ]);
    }
}
