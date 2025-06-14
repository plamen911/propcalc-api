<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\InsurancePolicy;
use App\Entity\InsurancePolicyPropertyChecklist;
use App\Entity\InsurancePolicyClause;
use App\Repository\InsurancePolicyRepository;
use App\Repository\InsurancePolicyPropertyChecklistRepository;
use App\Repository\SettlementRepository;
use App\Repository\EstateTypeRepository;
use App\Repository\WaterDistanceRepository;
use App\Repository\PersonRoleRepository;
use App\Repository\IdNumberTypeRepository;
use App\Repository\NationalityRepository;
use App\Repository\PropertyChecklistRepository;
use App\Repository\TariffPresetRepository;
use App\Repository\TariffPresetClauseRepository;
use App\Repository\InsuranceClauseRepository;
use App\Repository\InsurancePolicyClauseRepository;
use App\Service\EmailService;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/insurance-policies', name: 'api_v1_insurance_policies_')]
class InsurancePolicyController extends AbstractController
{
    private InsurancePolicyRepository $insurancePolicyRepository;
    private SettlementRepository $settlementRepository;
    private EstateTypeRepository $estateTypeRepository;
    private WaterDistanceRepository $waterDistanceRepository;
    private PersonRoleRepository $personRoleRepository;
    private IdNumberTypeRepository $idNumberTypeRepository;
    private NationalityRepository $nationalityRepository;
    private PropertyChecklistRepository $propertyChecklistRepository;
    private InsurancePolicyPropertyChecklistRepository $insurancePolicyPropertyChecklistRepository;
    private TariffPresetRepository $tariffPresetRepository;
    private TariffPresetClauseRepository $tariffPresetClauseRepository;
    private InsuranceClauseRepository $insuranceClauseRepository;
    private InsurancePolicyClauseRepository $insurancePolicyClauseRepository;
    private ValidatorInterface $validator;
    private EmailService $emailService;

    public function __construct(
        InsurancePolicyRepository $insurancePolicyRepository,
        SettlementRepository      $settlementRepository,
        EstateTypeRepository      $estateTypeRepository,
        WaterDistanceRepository   $waterDistanceRepository,
        PersonRoleRepository      $personRoleRepository,
        IdNumberTypeRepository    $idNumberTypeRepository,
        NationalityRepository     $nationalityRepository,
        PropertyChecklistRepository $propertyChecklistRepository,
        InsurancePolicyPropertyChecklistRepository $insurancePolicyPropertyChecklistRepository,
        TariffPresetRepository    $tariffPresetRepository,
        TariffPresetClauseRepository $tariffPresetClauseRepository,
        InsuranceClauseRepository $insuranceClauseRepository,
        InsurancePolicyClauseRepository $insurancePolicyClauseRepository,
        ValidatorInterface        $validator,
        EmailService              $emailService
    ) {
        $this->insurancePolicyRepository = $insurancePolicyRepository;
        $this->settlementRepository = $settlementRepository;
        $this->estateTypeRepository = $estateTypeRepository;
        $this->waterDistanceRepository = $waterDistanceRepository;
        $this->personRoleRepository = $personRoleRepository;
        $this->idNumberTypeRepository = $idNumberTypeRepository;
        $this->nationalityRepository = $nationalityRepository;
        $this->propertyChecklistRepository = $propertyChecklistRepository;
        $this->insurancePolicyPropertyChecklistRepository = $insurancePolicyPropertyChecklistRepository;
        $this->tariffPresetRepository = $tariffPresetRepository;
        $this->tariffPresetClauseRepository = $tariffPresetClauseRepository;
        $this->insuranceClauseRepository = $insuranceClauseRepository;
        $this->insurancePolicyClauseRepository = $insurancePolicyClauseRepository;
        $this->validator = $validator;
        $this->emailService = $emailService;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ORMException
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = [
            'settlement_id', 'estate_type_id', 'estate_subtype_id', 'distance_to_water_id', 'area_sq_meters',
            'person_role_id', 'id_number_type_id', 'insurer_settlement_id'
        ];
        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $errors[] = sprintf('The field "%s" is required.', $field);
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Validate settlement_id
        $settlement = $this->settlementRepository->find($data['settlement_id']);
        if (!$settlement) {
            $errors[] = sprintf('Settlement with ID %d not found.', $data['settlement_id']);
        }

        // Validate estate_type_id
        $estateType = $this->estateTypeRepository->find($data['estate_type_id']);
        if (!$estateType) {
            $errors[] = sprintf('Estate type with ID %d not found.', $data['estate_type_id']);
        }

        // Validate estate_subtype_id
        $estateSubtype = $this->estateTypeRepository->find($data['estate_subtype_id']);
        if (!$estateSubtype) {
            $errors[] = sprintf('Estate subtype with ID %d not found.', $data['estate_subtype_id']);
        }

        // Validate distance_to_water_id
        $distanceToWater = $this->waterDistanceRepository->find($data['distance_to_water_id']);
        if (!$distanceToWater) {
            $errors[] = sprintf('Distance to water with ID %d not found.', $data['distance_to_water_id']);
        }

        // Validate person_role_id
        $personRole = $this->personRoleRepository->find($data['person_role_id']);
        if (!$personRole) {
            $errors[] = sprintf('Person role with ID %d not found.', $data['person_role_id']);
        }

        // Validate id_number_type_id
        $idNumberType = $this->idNumberTypeRepository->find($data['id_number_type_id']);
        if (!$idNumberType) {
            $errors[] = sprintf('ID number type with ID %d not found.', $data['id_number_type_id']);
        }

        // Validate insurer_nationality_id if id_number_type_id is not 1
        if (isset($data['id_number_type_id']) && (int) $data['id_number_type_id'] != 1) {
            if (!isset($data['insurer_nationality_id'])) {
                $errors[] = 'The field "insurer_nationality_id" is required when ID number type is not 1.';
            } else {
                $insurerNationality = $this->nationalityRepository->find($data['insurer_nationality_id']);
                if (!$insurerNationality) {
                    $errors[] = sprintf('Nationality with ID %d not found.', $data['insurer_nationality_id']);
                }
            }
        } elseif (isset($data['insurer_nationality_id']) && (int) $data['insurer_nationality_id'] != 0) {
            // Only validate insurer_nationality_id if it's provided and not 0 when id_number_type_id = 1
            $insurerNationality = $this->nationalityRepository->find($data['insurer_nationality_id']);
            if (!$insurerNationality) {
                $errors[] = sprintf('Nationality with ID %d not found.', $data['insurer_nationality_id']);
            }
        }

        // Validate insurer_settlement_id
        $insurerSettlement = $this->settlementRepository->find($data['insurer_settlement_id']);
        if (!$insurerSettlement) {
            $errors[] = sprintf('Insurer settlement with ID %d not found.', $data['insurer_settlement_id']);
        }

        // Validate tariff_preset_id if provided
        $tariffPreset = null;
        if (isset($data['tariff_preset_id'])) {
            $tariffPreset = $this->tariffPresetRepository->find($data['tariff_preset_id']);
            if (!$tariffPreset) {
                $errors[] = sprintf('Tariff preset with ID %d not found.', $data['tariff_preset_id']);
            }
        }

        // Validate gender if provided
        if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female'])) {
            $errors[] = 'Gender must be either "male" or "female".';
        }

        // Validate area_sq_meters range
        $areaSqMeters = $data['area_sq_meters'];
        if (!is_numeric($areaSqMeters) || $areaSqMeters < 0 || $areaSqMeters > 100000) {
            $errors[] = 'Area in square meters must be a number between 0 and 100000.';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        // Create a new insurance policy
        $insurancePolicy = new InsurancePolicy();
        $insurancePolicy->setSettlement($settlement);
        $insurancePolicy->setEstateType($estateType);
        $insurancePolicy->setEstateSubtype($estateSubtype);
        $insurancePolicy->setDistanceToWater($distanceToWater);
        $insurancePolicy->setAreaSqMeters((float)$areaSqMeters);

        // Set new fields
        $insurancePolicy->setPersonRole($personRole);
        $insurancePolicy->setIdNumberType($idNumberType);

        // Set insurer_nationality_id to null if id_number_type_id = 1
        if (isset($data['id_number_type_id']) && $data['id_number_type_id'] == 1) {
            $insurancePolicy->setInsurerNationality(null);
        } else {
            $insurancePolicy->setInsurerNationality($insurerNationality);
        }

        $insurancePolicy->setInsurerSettlement($insurerSettlement);

        // Set optional fields if provided
        if (isset($data['full_name'])) {
            $insurancePolicy->setFullName($data['full_name']);
        }

        if (isset($data['id_number'])) {
            $insurancePolicy->setIdNumber($data['id_number']);
        }

        // Set birth_date to null if id_number_type_id = 1, otherwise use the provided value
        if (isset($data['id_number_type_id']) && $data['id_number_type_id'] == 1) {
            $insurancePolicy->setBirthDate(null);
        } else if (isset($data['birth_date'])) {
            $insurancePolicy->setBirthDate(new \DateTime($data['birth_date']));
        }

        if (isset($data['gender'])) {
            $insurancePolicy->setGender($data['gender']);
        }

        if (isset($data['permanent_address'])) {
            $insurancePolicy->setPermanentAddress($data['permanent_address']);
        }

        if (isset($data['phone'])) {
            $insurancePolicy->setPhone($data['phone']);
        }

        if (isset($data['email'])) {
            $insurancePolicy->setEmail($data['email']);
        }

        // Set financial fields based on the data from the request
        $insurancePolicy->setSubtotal(isset($data['subtotal']) ? (float)$data['subtotal'] : 0);
        $insurancePolicy->setDiscount(isset($data['discount']) ? (float)$data['discount'] : 0);
        $insurancePolicy->setSubtotalTax(isset($data['subtotal_tax']) ? (float)$data['subtotal_tax'] : 0);
        $insurancePolicy->setTotal(isset($data['total']) ? (float)$data['total'] : 0);

        // Handle tariff preset logic
        if ($tariffPreset) {
            // If the tariff selection is from a preset
            $insurancePolicy->setTariffPreset($tariffPreset);
            $insurancePolicy->setTariffPresetName($tariffPreset->getName());
        } else {
            // If the selected a custom tariff
            $insurancePolicy->setTariffPreset(null);
            $insurancePolicy->setTariffPresetName('Пакет по избор');
        }

        // Set a temporary code to satisfy the NOT NULL constraint
        $tempCode = 'TEMP-' . uniqid();
        $insurancePolicy->setCode($tempCode);

        // First save the insurance policy to get the database ID
        $this->insurancePolicyRepository->save($insurancePolicy, true);

        // Get the database ID
        $id = $insurancePolicy->getId();

        // Count policies for the current day
        $today = new \DateTime();
        $count = $this->insurancePolicyRepository->countPoliciesForDate($today);

        // Generate a unique code for the insurance policy
        $code = $this->generateUniqueCode($id, $count);
        $insurancePolicy->setCode($code);

        // Save the insurance policy again with the generated code
        $this->insurancePolicyRepository->save($insurancePolicy, true);

        // Handle insurance policy clauses
        if ($tariffPreset) {
            // If the tariff selection is from a preset, loop through tariff_preset_clauses
            $tariffPresetClauses = $this->tariffPresetClauseRepository->findBy(['tariffPreset' => $tariffPreset]);
            foreach ($tariffPresetClauses as $tariffPresetClause) {
                $insuranceClause = $tariffPresetClause->getInsuranceClause();

                $policyClause = new InsurancePolicyClause();
                $policyClause->setInsurancePolicy($insurancePolicy);
                $policyClause->setInsuranceClause($insuranceClause);
                $policyClause->setName($insuranceClause->getName());
                $policyClause->setTariffNumber($insuranceClause->getTariffNumber());
                $policyClause->setTariffAmount($tariffPresetClause->getTariffAmount());
                $policyClause->setPosition($tariffPresetClause->getPosition());

                $this->insurancePolicyClauseRepository->save($policyClause, true);
            }
        } else {
            // If the selected a custom tariff, loop through insurance_clauses
            $insuranceClauses = $this->insuranceClauseRepository->findAll();
            foreach ($insuranceClauses as $position => $insuranceClause) {
                $policyClause = new InsurancePolicyClause();
                $policyClause->setInsurancePolicy($insurancePolicy);
                $policyClause->setInsuranceClause($insuranceClause);
                $policyClause->setName($insuranceClause->getName());
                $policyClause->setTariffNumber($insuranceClause->getTariffNumber());

                // Respect user entered tariff_amount
                $tariffAmount = 0;
                if (isset($data['tariff_amounts']) && isset($data['tariff_amounts'][$insuranceClause->getId()])) {
                    $tariffAmount = (float)$data['tariff_amounts'][$insuranceClause->getId()];
                } else {
                    $tariffAmount = $insuranceClause->getTariffAmount();
                }

                $policyClause->setTariffAmount($tariffAmount);
                $policyClause->setPosition($position + 1);

                $this->insurancePolicyClauseRepository->save($policyClause, true);
            }
        }

        // Handle property checklist items if provided
        if (isset($data['property_checklist_items']) && is_array($data['property_checklist_items'])) {
            foreach ($data['property_checklist_items'] as $checklistItemId => $value) {
                $propertyChecklist = $this->propertyChecklistRepository->find($checklistItemId);
                if ($propertyChecklist) {
                    $policyPropertyChecklist = new InsurancePolicyPropertyChecklist();
                    $policyPropertyChecklist->setInsurancePolicy($insurancePolicy);
                    $policyPropertyChecklist->setPropertyChecklist($propertyChecklist);
                    $policyPropertyChecklist->setName($propertyChecklist->getName());
                    $policyPropertyChecklist->setValue((bool)$value);
                    $this->insurancePolicyPropertyChecklistRepository->save($policyPropertyChecklist, true);
                }
            }
        }

        // Return the created insurance policy
        $response = [
            'id' => $insurancePolicy->getId(),
            'code' => $insurancePolicy->getCode(),
            'settlement_id' => $insurancePolicy->getSettlement()->getId(),
            'estate_type_id' => $insurancePolicy->getEstateType()->getId(),
            'estate_subtype_id' => $insurancePolicy->getEstateSubtype()->getId(),
            'distance_to_water_id' => $insurancePolicy->getDistanceToWater()->getId(),
            'area_sq_meters' => $insurancePolicy->getAreaSqMeters(),
            'person_role_id' => $insurancePolicy->getPersonRole()->getId(),
            'id_number_type_id' => $insurancePolicy->getIdNumberType()->getId(),
            'insurer_settlement_id' => $insurancePolicy->getInsurerSettlement()->getId(),
            'subtotal' => $insurancePolicy->getSubtotal(),
            'discount' => $insurancePolicy->getDiscount(),
            'subtotal_tax' => $insurancePolicy->getSubtotalTax(),
            'total' => $insurancePolicy->getTotal(),
            'created_at' => $insurancePolicy->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $insurancePolicy->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        // Add tariff_preset_id to the response if it's set
        if ($insurancePolicy->getTariffPreset()) {
            $response['tariff_preset_id'] = $insurancePolicy->getTariffPreset()->getId();
        }

        // Add insurer_nationality_id to the response if it's set
        if ($insurancePolicy->getInsurerNationality()) {
            $response['insurer_nationality_id'] = $insurancePolicy->getInsurerNationality()->getId();
        }

        // Add tariff_preset_name to the response if it's set
        if ($insurancePolicy->getTariffPresetName() !== null) {
            $response['tariff_preset_name'] = $insurancePolicy->getTariffPresetName();
        }

        // Add property checklist items to the response
        $propertyChecklistItems = [];
        $policyPropertyChecklists = $this->insurancePolicyPropertyChecklistRepository->findBy(['insurancePolicy' => $insurancePolicy]);
        foreach ($policyPropertyChecklists as $policyPropertyChecklist) {
            $propertyChecklistItems[$policyPropertyChecklist->getPropertyChecklist()->getId()] = [
                'value' => $policyPropertyChecklist->getValue(),
                'name' => $policyPropertyChecklist->getName()
            ];
        }
        $response['property_checklist_items'] = $propertyChecklistItems;

        // Add insurance policy clauses to the response
        $policyClauses = [];
        $insurancePolicyClauses = $this->insurancePolicyClauseRepository->findBy(['insurancePolicy' => $insurancePolicy]);
        foreach ($insurancePolicyClauses as $insurancePolicyClause) {
            $policyClauses[] = [
                'id' => $insurancePolicyClause->getId(),
                'name' => $insurancePolicyClause->getName(),
                'tariff_number' => $insurancePolicyClause->getTariffNumber(),
                'tariff_amount' => $insurancePolicyClause->getTariffAmount(),
                'position' => $insurancePolicyClause->getPosition(),
                'insurance_clause_id' => $insurancePolicyClause->getInsuranceClause()?->getId(),
            ];
        }
        $response['insurance_policy_clauses'] = $policyClauses;

        // Add optional fields if they are set
        if ($insurancePolicy->getFullName() !== null) {
            $response['full_name'] = $insurancePolicy->getFullName();
        }

        if ($insurancePolicy->getIdNumber() !== null) {
            $response['id_number'] = $insurancePolicy->getIdNumber();
        }

        if ($insurancePolicy->getBirthDate() !== null) {
            $response['birth_date'] = $insurancePolicy->getBirthDate()->format('Y-m-d');
        }

        if ($insurancePolicy->getGender() !== null) {
            $response['gender'] = $insurancePolicy->getGender();
        }

        if ($insurancePolicy->getPermanentAddress() !== null) {
            $response['permanent_address'] = $insurancePolicy->getPermanentAddress();
        }

        if ($insurancePolicy->getPhone() !== null) {
            $response['phone'] = $insurancePolicy->getPhone();
        }

        if ($insurancePolicy->getEmail() !== null) {
            $response['email'] = $insurancePolicy->getEmail();
        }

        // Load the insurance policy with its clauses before sending emails
        $entityManager = $this->insurancePolicyRepository->getEntityManager();
        $entityManager->refresh($insurancePolicy);
        // Ensure the clauses are loaded
        $insurancePolicy->getInsurancePolicyClauses();

        // Send confirmation emails to client and admin
        // This is done in a way that doesn't block the workflow even if email sending fails
        try {
            $this->emailService->sendOrderConfirmationEmails($insurancePolicy);
        } catch (\Exception $e) {
            // Just log the error, don't block the workflow
            // The error is already logged in the EmailService
        }

        return $this->json($response, Response::HTTP_CREATED);
    }

    /**
     * Generate a unique code for an insurance policy
     *
     * @param int $id The database ID of the insurance policy
     * @param int $count The count of policies for the current day
     * @return string
     */
    private function generateUniqueCode(int $id, int $count): string
    {
        // Format: И + padding with '0' for 10 + database ID + current date (ymd) + count of policies for the day
        $prefix = 'P';
        $date = new \DateTime();
        $dateString = $date->format('ymd');

        // Combine the parts: prefix + ID + date + count
        $baseCode = $prefix . $id . $dateString . $count;

        // Calculate padding needed to make it 10 characters after the prefix
        $paddingLength = 10 - strlen($baseCode) + 1; // +1 because we're replacing the prefix
        if ($paddingLength > 0) {
            $padding = str_repeat('0', $paddingLength);
            // Insert padding after the prefix
            $code = $prefix . $padding . $id . $dateString . $count;
        } else {
            $code = $baseCode;
        }

        return $code;
    }
}
