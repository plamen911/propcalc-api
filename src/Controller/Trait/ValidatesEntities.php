<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ValidatesEntities
{
    private function validationErrors(ConstraintViolationListInterface $errors): ?JsonResponse
    {
        if (count($errors) === 0) {
            return null;
        }

        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }

        return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
    }
}
