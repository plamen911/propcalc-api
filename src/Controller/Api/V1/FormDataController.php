<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Repository\EstateTypeRepository;
use App\Repository\WaterDistanceRepository;
use App\Repository\PersonRoleRepository;
use App\Repository\IdNumberTypeRepository;
use App\Repository\PropertyChecklistRepository;
use App\Repository\SettlementRepository;
use App\Repository\NationalityRepository;
use App\Repository\AppConfigRepository;
use App\Repository\InsuranceClauseRepository;
use App\Service\TariffPresetService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/form-data')]
class FormDataController extends AbstractController
{
    private TariffPresetService $tariffPresetService;
    private AppConfigRepository $appConfigRepository;
    private InsuranceClauseRepository $insuranceClauseRepository;

    public function __construct(
        TariffPresetService $tariffPresetService,
        AppConfigRepository $appConfigRepository,
        InsuranceClauseRepository $insuranceClauseRepository
    ) {
        $this->tariffPresetService = $tariffPresetService;
        $this->appConfigRepository = $appConfigRepository;
        $this->insuranceClauseRepository = $insuranceClauseRepository;
    }
    // Endpoint removed in favor of /api/v1/form-data/initial-data

    #[Route('/estate-subtypes/{parentId}', name: 'api_v1_form_data_estate_subtypes', methods: ['GET'])]
    public function getEstateSubtypes(int $parentId, EstateTypeRepository $estateTypeRepository): JsonResponse
    {
        $estateSubtypes = $estateTypeRepository->findBy(['parent' => $parentId], ['position' => 'ASC']);

        $data = [];
        foreach ($estateSubtypes as $estateSubtype) {
            $data[] = [
                'id' => $estateSubtype->getId(),
                'name' => $estateSubtype->getName(),
                'code' => $estateSubtype->getCode(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/settlements', name: 'api_v1_form_data_settlements', methods: ['GET'])]
    public function getSettlements(Request $request, SettlementRepository $settlementRepository): JsonResponse
    {
        // Check if we're looking for a specific settlement by ID
        if ($request->query->has('id')) {
            $id = $request->query->get('id');
            $settlement = $settlementRepository->find($id);

            if (!$settlement) {
                return $this->json([]);
            }

            return $this->json([
                [
                    'id' => $settlement->getId(),
                    'name' => $settlement->getType()->getName().' '. $settlement->getName().' ('
                        .'Общ. '.$settlement->getMunicipality()->getName().', Обл. '.$settlement->getRegion()->getName()
                        .')',
                    'post_code' => $settlement->getPostCode(),
                ]
            ]);
        }

        // Otherwise search by name or postal code
        $query = $request->query->get('query', '');
        $limit = $request->query->get('limit', 10);

        $settlements = $settlementRepository->findByNameOrPostalCode($query, (int)$limit);

        $data = [];
        foreach ($settlements as $settlement) {
            $data[] = [
                'id' => $settlement->getId(),
                'name' => $settlement->getFullName(),
                'post_code' => $settlement->getPostCode(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/person-role', name: 'api_v1_form_data_person_role', methods: ['GET'])]
    public function getPersonRole(PersonRoleRepository $personRoleRepository): JsonResponse
    {
        $personRoleOptions = $personRoleRepository->findBy([], ['position' => 'ASC']);

        $data = [];
        foreach ($personRoleOptions as $option) {
            $data[] = [
                'id' => $option->getId(),
                'name' => $option->getName(),
                'position' => $option->getPosition(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/id-number-type', name: 'api_v1_form_data_id_number_type', methods: ['GET'])]
    public function getIdNumberType(IdNumberTypeRepository $idNumberTypeRepository): JsonResponse
    {
        $idNumberTypeOptions = $idNumberTypeRepository->findBy([], ['position' => 'ASC']);

        $data = [];
        foreach ($idNumberTypeOptions as $option) {
            $data[] = [
                'id' => $option->getId(),
                'name' => $option->getName(),
                'position' => $option->getPosition(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/property-checklist', name: 'api_v1_form_data_property_checklist', methods: ['GET'])]
    public function getPropertyChecklist(PropertyChecklistRepository $propertyChecklistRepository): JsonResponse
    {
        $propertyChecklistItems = $propertyChecklistRepository->findBy([], ['position' => 'ASC']);

        $data = [];
        foreach ($propertyChecklistItems as $item) {
            $data[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'position' => $item->getPosition(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/nationalities', name: 'api_v1_form_data_nationalities', methods: ['GET'])]
    public function getNationalities(NationalityRepository $nationalityRepository): JsonResponse
    {
        $nationalities = $nationalityRepository->findBy([], ['position' => 'ASC']);

        $data = [];
        foreach ($nationalities as $nationality) {
            $data[] = [
                'id' => $nationality->getId(),
                'name' => $nationality->getName(),
                'position' => $nationality->getPosition(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/custom-package-statistics', name: 'api_v1_form_data_custom_package_statistics', methods: ['POST'])]
    public function calculateCustomPackageStatistics(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required parameters
        if (!isset($data['custom_clause_amounts']) || !is_array($data['custom_clause_amounts'])) {
            return $this->json(['error' => 'Missing or invalid custom_clause_amounts parameter'], Response::HTTP_BAD_REQUEST);
        }

        // Validate insurance_clause_id = 1 (max value should be 2000000)
        if (isset($data['custom_clause_amounts'][1]) && is_numeric($data['custom_clause_amounts'][1])) {
            $clause1Value = (float) $data['custom_clause_amounts'][1];
            if ($clause1Value > 2000000) {
                return $this->json(['error' => 'Value for insurance_clause_id = 1 cannot exceed 2000000'], Response::HTTP_BAD_REQUEST);
            }
        }

        $customClauseAmounts = $data['custom_clause_amounts'];
        $settlementId = isset($data['settlement_id']) ? (int) $data['settlement_id'] : null;
        $distanceToWaterId = isset($data['distance_to_water_id']) ? (int) $data['distance_to_water_id'] : null;

        // Calculate statistics using the service
        $statistics = $this->tariffPresetService->calculateCustomPackageStatistics(
            $customClauseAmounts,
            $settlementId,
            $distanceToWaterId
        );

        return $this->json($statistics);
    }

    #[Route('/app-config', name: 'api_v1_form_data_app_config', methods: ['GET'])]
    public function getAppConfig(Request $request): JsonResponse
    {
        $name = $request->query->get('name');

        if (!$name) {
            return $this->json(['error' => 'Missing name parameter'], Response::HTTP_BAD_REQUEST);
        }

        $config = $this->appConfigRepository->findOneBy(['name' => $name]);

        if (!$config) {
            return $this->json(['error' => 'Config not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'name' => $config->getName(),
            'value' => $config->getValue()
        ]);
    }

    #[Route('/clause-config', name: 'api_v1_form_data_clause_config', methods: ['GET'])]
    public function getClauseConfig(): JsonResponse
    {
        $result = [];

        $clauses = $this->insuranceClauseRepository->findAllWithMinAndMaxValues();
        foreach ($clauses as $clause) {
            $result[$clause->getId()] = [
                'min' => $clause->getMinValue(),
                'max' => $clause->getMaxValue(),
                'step' => $clause->getStepValue(),
            ];
        }

        return $this->json($result);
    }

    #[Route('/initial-data', name: 'api_v1_form_data_initial_data', methods: ['GET'])]
    public function getInitialData(
        EstateTypeRepository $estateTypeRepository,
        WaterDistanceRepository $waterDistanceRepository,
        PersonRoleRepository $personRoleRepository,
        IdNumberTypeRepository $idNumberTypeRepository,
        PropertyChecklistRepository $propertyChecklistRepository
    ): JsonResponse
    {
        // Get estate types
        $estateTypes = $estateTypeRepository->findBy(['parent' => null], ['position' => 'ASC']);
        $estateTypesData = [];
        foreach ($estateTypes as $estateType) {
            $estateTypesData[] = [
                'id' => $estateType->getId(),
                'name' => $estateType->getName(),
                'code' => $estateType->getCode(),
            ];
        }

        // Get water distances
        $distances = $waterDistanceRepository->findBy([], ['position' => 'ASC']);
        $waterDistancesData = [];
        foreach ($distances as $distance) {
            $waterDistancesData[] = [
                'id' => $distance->getId(),
                'name' => $distance->getName(),
                'code' => $distance->getCode(),
            ];
        }

        // Get currency symbol
        $currencyConfig = $this->appConfigRepository->findOneBy(['name' => 'CURRENCY']);
        $currencySymbol = $currencyConfig ? $currencyConfig->getValue() : '';

        // Get person role options
        $personRoleOptions = $personRoleRepository->findBy([], ['position' => 'ASC']);
        $personRoleData = [];
        foreach ($personRoleOptions as $option) {
            $personRoleData[] = [
                'id' => $option->getId(),
                'name' => $option->getName(),
                'position' => $option->getPosition(),
            ];
        }

        // Get ID number type options
        $idNumberTypeOptions = $idNumberTypeRepository->findBy([], ['position' => 'ASC']);
        $idNumberTypeData = [];
        foreach ($idNumberTypeOptions as $option) {
            $idNumberTypeData[] = [
                'id' => $option->getId(),
                'name' => $option->getName(),
                'position' => $option->getPosition(),
            ];
        }

        // Get property checklist items
        $propertyChecklistItems = $propertyChecklistRepository->findBy([], ['position' => 'ASC']);
        $propertyChecklistData = [];
        foreach ($propertyChecklistItems as $item) {
            $propertyChecklistData[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'position' => $item->getPosition(),
            ];
        }

        // Return all data in a single response
        return $this->json([
            'estate_types' => $estateTypesData,
            'water_distances' => $waterDistancesData,
            'currency_symbol' => $currencySymbol,
            'person_role_options' => $personRoleData,
            'id_number_type_options' => $idNumberTypeData,
            'property_checklist_items' => $propertyChecklistData,
            'document_links' => self::getDocumentLinks(),
        ]);
    }

    // Document links
    public static function getDocumentLinks(): array
    {
        return [
            [
                'uri' => 'https://propcalc.zastrahovaite.com/docs/propcalc/pre-contract-insurance-info.pdf',
                'title' => 'Информация преди сключване на застрахователния договор'
            ],
            [
                'uri' => 'https://propcalc.zastrahovaite.com/docs/propcalc/property-insurance-product-info.pdf',
                'title' => 'Информационен документ за застрахователния продукт Бонус дом+'
            ],
            [
                'uri' => 'https://propcalc.zastrahovaite.com/docs/propcalc/bonus-home-plus.pdf',
                'title' => 'Общи условия по застраховка Бонус дом+'
            ]
        ];
    }
}
