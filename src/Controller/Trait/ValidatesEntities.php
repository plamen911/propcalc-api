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

    /**
     * Payload-shape errors, keyed by the field they belong to.
     *
     * Same 'errors' envelope as validationErrors() above, but the value is a map of
     * field => messages rather than a flat list, and the status is 422: the request was
     * understood and well-formed JSON, its contents were unacceptable. Use this for
     * checks made before an entity is populated, where a bad value would otherwise
     * reach a typed setter or a NOT NULL column.
     *
     * @param array<string, list<string>> $fieldErrors
     */
    private function fieldValidationErrors(array $fieldErrors): ?JsonResponse
    {
        if ($fieldErrors === []) {
            return null;
        }

        return new JsonResponse(['errors' => $fieldErrors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
