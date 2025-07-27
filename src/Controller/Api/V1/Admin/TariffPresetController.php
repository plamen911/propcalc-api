<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

use App\Entity\TariffPreset;
use App\Entity\TariffPresetClause;
use App\Repository\InsuranceClauseRepository;
use App\Repository\TariffPresetRepository;
use App\Repository\TariffPresetClauseRepository;
use App\Service\TariffPresetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/insurance-policies/admin', name: 'api_v1_insurance_policies_admin_')]
class TariffPresetController extends AbstractController
{
    private InsuranceClauseRepository $insuranceClauseRepository;
    private TariffPresetRepository $tariffPresetRepository;
    private TariffPresetClauseRepository $tariffPresetClauseRepository;
    private ValidatorInterface $validator;
    private TariffPresetService $tariffPresetService;

    public function __construct(
        InsuranceClauseRepository $insuranceClauseRepository,
        TariffPresetRepository $tariffPresetRepository,
        TariffPresetClauseRepository $tariffPresetClauseRepository,
        ValidatorInterface $validator,
        TariffPresetService $tariffPresetService
    ) {
        $this->insuranceClauseRepository = $insuranceClauseRepository;
        $this->tariffPresetRepository = $tariffPresetRepository;
        $this->tariffPresetClauseRepository = $tariffPresetClauseRepository;
        $this->validator = $validator;
        $this->tariffPresetService = $tariffPresetService;
    }

    #[Route('/tariff-presets', name: 'tariff_presets_list', methods: ['GET'])]
    public function listTariffPresets(Request $request): JsonResponse
    {
        // Get optional filter parameters from the request
        $settlementId = $request->query->get('settlement_id') ? $request->query->getInt('settlement_id') : null;
        $distanceToWaterId = $request->query->get('distance_to_water_id') ? $request->query->getInt('distance_to_water_id') : null;
        $areaSqMeters = $request->query->get('area_sq_meters') ? $request->query->getInt('area_sq_meters') : 0;

        // Use the service to get the tariff presets
        $data = $this->tariffPresetService->getTariffPresets($settlementId, $distanceToWaterId);

        if ($areaSqMeters > 0) {
            $data = array_slice(
                array_values(
                    array_filter($data, function (array $item) use ($areaSqMeters) {
                        return !empty($item['tariff_preset_clauses'])
                            && is_array($item['tariff_preset_clauses'])
                            && isset($item['tariff_preset_clauses'][0]['tariff_amount'])
                            && ((int)$item['tariff_preset_clauses'][0]['tariff_amount']) >= $areaSqMeters * 1000;
                    })
                ), 0, 5);
        }

        return $this->json($data);
    }

    #[Route('/tariff-presets', name: 'tariff_presets_create', methods: ['POST'])]
    public function createTariffPreset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Create a new tariff preset
        $tariffPreset = new TariffPreset();

        if (isset($data['name'])) {
            $tariffPreset->setName($data['name']);
        }

        if (isset($data['active'])) {
            $tariffPreset->setActive($data['active']);
        } else {
            $tariffPreset->setActive(true); // Default to active
        }

        // Set position to the end of the list
        $lastPosition = 0;
        $lastPreset = $this->tariffPresetRepository->findOneBy([], ['position' => 'DESC']);
        if ($lastPreset) {
            $lastPosition = $lastPreset->getPosition();
        }
        $tariffPreset->setPosition($lastPosition + 1);

        $errors = $this->validator->validate($tariffPreset);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->tariffPresetRepository->save($tariffPreset, true);

        // Create tariff preset clauses if provided
        if (isset($data['tariff_preset_clauses']) && is_array($data['tariff_preset_clauses'])) {
            $position = 1;
            foreach ($data['tariff_preset_clauses'] as $clauseData) {
                if (isset($clauseData['insurance_clause']['id'])) {
                    $insuranceClause = $this->insuranceClauseRepository->find($clauseData['insurance_clause']['id']);

                    if ($insuranceClause && $insuranceClause->isActive()) {
                        $tariffPresetClause = new TariffPresetClause();
                        $tariffPresetClause->setTariffPreset($tariffPreset);
                        $tariffPresetClause->setInsuranceClause($insuranceClause);

                        if (isset($clauseData['tariff_amount'])) {
                            $tariffPresetClause->setTariffAmount((float)$clauseData['tariff_amount']);
                        } else {
                            $tariffPresetClause->setTariffAmount(0);
                        }

                        $tariffPresetClause->setPosition($position++);

                        $this->tariffPresetClauseRepository->save($tariffPresetClause, true);
                    }
                }
            }
        }

        // Get created tariff preset with clauses (only active insurance clauses)
        $tariffPresetClauses = $this->tariffPresetClauseRepository->findByTariffPresetWithActiveInsuranceClauses(
            $tariffPreset
        );

        $responseData = [
            'id' => $tariffPreset->getId(),
            'name' => $tariffPreset->getName(),
            'active' => $tariffPreset->isActive(),
            'position' => $tariffPreset->getPosition(),
            'tariff_preset_clauses' => [],
        ];

        foreach ($tariffPresetClauses as $clause) {
            $responseData['tariff_preset_clauses'][] = [
                'id' => $clause->getId(),
                'insurance_clause' => [
                    'id' => $clause->getInsuranceClause()->getId(),
                    'name' => $clause->getInsuranceClause()->getName(),
                ],
                'tariff_amount' => number_format($clause->getTariffAmount(), 2, '.', ''),
            ];
        }

        return $this->json($responseData, Response::HTTP_CREATED);
    }

    #[Route('/tariff-presets/{id}', name: 'tariff_presets_update', methods: ['PUT'])]
    public function updateTariffPreset(Request $request, int $id): JsonResponse
    {
        $tariffPreset = $this->tariffPresetRepository->find($id);

        if (!$tariffPreset) {
            return $this->json(['error' => 'Tariff preset not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $tariffPreset->setName($data['name']);
        }

        if (isset($data['active'])) {
            $tariffPreset->setActive($data['active']);
        }

        if (isset($data['position'])) {
            $tariffPreset->setPosition($data['position']);
        }

        $errors = $this->validator->validate($tariffPreset);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->tariffPresetRepository->save($tariffPreset, true);

        // Update tariff preset clauses if provided
        if (isset($data['tariff_preset_clauses']) && is_array($data['tariff_preset_clauses'])) {
            foreach ($data['tariff_preset_clauses'] as $clauseData) {
                if (isset($clauseData['id'])) {
                    // Update existing clause
                    $clause = $this->tariffPresetClauseRepository->find($clauseData['id']);
                    if ($clause && $clause->getTariffPreset()->getId() === $tariffPreset->getId()) {
                        if (isset($clauseData['tariff_amount'])) {
                            $clause->setTariffAmount((float)$clauseData['tariff_amount']);
                        }
                        $this->tariffPresetClauseRepository->save($clause, true);
                    }
                }
            }
        }

        // Get updated tariff preset with clauses (only active insurance clauses)
        $tariffPresetClauses = $this->tariffPresetClauseRepository->findByTariffPresetWithActiveInsuranceClauses(
            $tariffPreset
        );

        $responseData = [
            'id' => $tariffPreset->getId(),
            'name' => $tariffPreset->getName(),
            'active' => $tariffPreset->isActive(),
            'position' => $tariffPreset->getPosition(),
            'tariff_preset_clauses' => [],
        ];

        foreach ($tariffPresetClauses as $clause) {
            $responseData['tariff_preset_clauses'][] = [
                'id' => $clause->getId(),
                'insurance_clause' => [
                    'id' => $clause->getInsuranceClause()->getId(),
                    'name' => $clause->getInsuranceClause()->getName(),
                ],
                'tariff_amount' => number_format($clause->getTariffAmount(), 2, '.', ''),
            ];
        }

        return $this->json($responseData);
    }

    #[Route('/tariff-presets/{id}', name: 'tariff_presets_delete', methods: ['DELETE'])]
    public function deleteTariffPreset(int $id): JsonResponse
    {
        $tariffPreset = $this->tariffPresetRepository->find($id);

        if (!$tariffPreset) {
            return $this->json(['error' => 'Tariff preset not found'], Response::HTTP_NOT_FOUND);
        }

        // Delete associated tariff preset clauses
        $tariffPresetClauses = $this->tariffPresetClauseRepository->findBy(['tariffPreset' => $tariffPreset]);
        foreach ($tariffPresetClauses as $clause) {
            $this->tariffPresetClauseRepository->remove($clause, true);
        }

        // Delete the tariff preset
        $this->tariffPresetRepository->remove($tariffPreset, true);

        return $this->json(['success' => true]);
    }

    #[Route('/tariff-preset-clauses', name: 'tariff_preset_clauses_list', methods: ['GET'])]
    public function listTariffPresetClauses(): JsonResponse
    {
        $tariffPresetClauses = $this->tariffPresetClauseRepository->findAllWithActiveInsuranceClauses();

        $data = [];
        foreach ($tariffPresetClauses as $clause) {
            $data[] = [
                'id' => $clause->getId(),
                'tariff_preset' => [
                    'id' => $clause->getTariffPreset()->getId(),
                    'name' => $clause->getTariffPreset()->getName(),
                ],
                'insurance_clause' => [
                    'id' => $clause->getInsuranceClause()->getId(),
                    'name' => $clause->getInsuranceClause()->getName(),
                ],
                'tariff_amount' => number_format($clause->getTariffAmount(), 2, '.', ''),
                'position' => $clause->getPosition(),
            ];
        }

        return $this->json($data);
    }


    #[Route('/tariff-preset-clauses/{id}', name: 'tariff_preset_clauses_update', methods: ['PUT'])]
    public function updateTariffPresetClause(Request $request, int $id): JsonResponse
    {
        $tariffPresetClause = $this->tariffPresetClauseRepository->find($id);

        if (!$tariffPresetClause) {
            return $this->json(['error' => 'Tariff preset clause not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['tariff_preset_id'])) {
            $tariffPreset = $this->tariffPresetRepository->find($data['tariff_preset_id']);
            if (!$tariffPreset) {
                return $this->json(['error' => 'Tariff preset not found'], Response::HTTP_BAD_REQUEST);
            }
            $tariffPresetClause->setTariffPreset($tariffPreset);
        }

        if (isset($data['insurance_clause_id'])) {
            $insuranceClause = $this->insuranceClauseRepository->find($data['insurance_clause_id']);
            if (!$insuranceClause) {
                return $this->json(['error' => 'Insurance clause not found'], Response::HTTP_BAD_REQUEST);
            }
            $tariffPresetClause->setInsuranceClause($insuranceClause);
        }

        if (isset($data['tariff_amount'])) {
            $tariffPresetClause->setTariffAmount((float)$data['tariff_amount']);
        }

        if (isset($data['position'])) {
            $tariffPresetClause->setPosition($data['position']);
        }

        $errors = $this->validator->validate($tariffPresetClause);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->tariffPresetClauseRepository->save($tariffPresetClause, true);

        return $this->json([
            'id' => $tariffPresetClause->getId(),
            'tariff_preset' => [
                'id' => $tariffPresetClause->getTariffPreset()->getId(),
                'name' => $tariffPresetClause->getTariffPreset()->getName(),
            ],
            'insurance_clause' => [
                'id' => $tariffPresetClause->getInsuranceClause()->getId(),
                'name' => $tariffPresetClause->getInsuranceClause()->getName(),
            ],
            'tariff_amount' => number_format($tariffPresetClause->getTariffAmount(), 2, '.', ''),
            'position' => $tariffPresetClause->getPosition(),
        ]);
    }
}
