<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Admin;

use App\Controller\Trait\ValidatesEntities;
use App\Entity\AppConfig;
use App\Repository\AppConfigRepository;
use App\Repository\InsuranceClauseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/app-configs/admin', name: 'api_v1_app_configs_admin_')]
class AppConfigController extends AbstractController
{
    use ValidatesEntities;

    private AppConfigRepository $appConfigRepository;
    private InsuranceClauseRepository $insuranceClauseRepository;
    private ValidatorInterface $validator;

    public function __construct(
        AppConfigRepository $appConfigRepository,
        InsuranceClauseRepository $insuranceClauseRepository,
        ValidatorInterface $validator
    ) {
        $this->appConfigRepository = $appConfigRepository;
        $this->insuranceClauseRepository = $insuranceClauseRepository;
        $this->validator = $validator;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function listAppConfigs(): JsonResponse
    {
        $appConfigs = $this->appConfigRepository->findBy(['isEditable' => true], ['position' => 'ASC']);

        $data = [];
        foreach ($appConfigs as $config) {
            $data[] = [
                'id' => $config->getId(),
                'name' => $config->getName(),
                'value' => $config->getValue(),
                'nameBg' => $config->getNameBg(),
                'isEditable' => $config->isEditable(),
                'position' => $config->getPosition(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function updateAppConfig(Request $request, int $id): JsonResponse
    {
        $appConfig = $this->appConfigRepository->find($id);

        if (!$appConfig) {
            return $this->json(['error' => 'App config not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the app config is editable
        if (!$appConfig->isEditable()) {
            return $this->json(['error' => 'This app config is not editable'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $appConfig->setName($data['name']);
        }

        if (isset($data['value'])) {
            // Special validation for specific config types
            $this->validateAppConfigValue($appConfig, $data['value']);
            $appConfig->setValue($data['value']);
        }

        if (isset($data['nameBg'])) {
            $appConfig->setNameBg($data['nameBg']);
        }

        if ($errorResponse = $this->validationErrors($this->validator->validate($appConfig))) {
            return $errorResponse;
        }

        $this->appConfigRepository->save($appConfig, true);

        return $this->json([
            'id' => $appConfig->getId(),
            'name' => $appConfig->getName(),
            'value' => $appConfig->getValue(),
            'nameBg' => $appConfig->getNameBg(),
            'isEditable' => $appConfig->isEditable(),
            'position' => $appConfig->getPosition(),
        ]);
    }

    /**
     * Validate app config value based on its name
     */
    private function validateAppConfigValue(AppConfig $appConfig, string $value): void
    {
        if ($value === null) {
            return;
        }

        switch ($appConfig->getName()) {
            case 'CURRENCY':
                if (!in_array($value, ['лв.', '€'])) {
                    throw new \InvalidArgumentException('Currency must be either лв. or €');
                }
                break;
            case 'DISCOUNT_PERCENTS':
            case 'TAX_PERCENTS':
                $intValue = (int) $value;
                if ($intValue < 0 || $intValue > 100) {
                    throw new \InvalidArgumentException('Percentage must be between 0 and 100');
                }
                break;
            case 'EARTHQUAKE_ID':
            case 'FLOOD_LT_500_M_ID':
            case 'FLOOD_GT_500_M_ID':
                $insuranceClause = $this->insuranceClauseRepository->find((int) $value);
                if (!$insuranceClause) {
                    throw new \InvalidArgumentException('Insurance clause not found');
                }
                break;
        }
    }
}
