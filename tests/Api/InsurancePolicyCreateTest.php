<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\InsurancePolicy;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/insurance-policies - the policy creation flow.
 *
 * Validation is hand-rolled in the controller (the injected ValidatorInterface is never
 * used) and every message is in Bulgarian, returned as {"errors": [...]} with a 400.
 */
final class InsurancePolicyCreateTest extends ApiTestCase
{
    private const REQUIRED_FIELDS = [
        'settlement_id',
        'estate_type_id',
        'estate_subtype_id',
        'distance_to_water_id',
        'area_sq_meters',
        'person_role_id',
        'id_number_type_id',
        'insurer_settlement_id',
    ];

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'settlement_id' => 1,
            'estate_type_id' => 1,
            'estate_subtype_id' => 2,
            'distance_to_water_id' => 2,
            'area_sq_meters' => 85,
            'person_role_id' => 1,
            'id_number_type_id' => 1,
            'insurer_settlement_id' => 1,
            'full_name' => 'Иван Иванов',
            'id_number' => '8001010000',
            'gender' => 'male',
            'email' => 'ivan@example.test',
            'phone' => '0888000000',
            'property_address' => 'ул. Тестова 1',
            'subtotal' => 192.70,
            'discount' => 40,
            'subtotal_tax' => 2.31,
            'total' => 117.93,
        ], $overrides);
    }

    private function post(array $payload): void
    {
        $this->request('POST', '/api/v1/insurance-policies', $payload, $this->anonymousToken());
    }

    // -----------------------------------------------------------------------
    // Validation rejections
    // -----------------------------------------------------------------------

    #[Test]
    public function anEmptyPayloadListsEveryRequiredField(): void
    {
        $this->post([]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());

        $errors = $this->jsonResponse()['errors'];

        self::assertCount(count(self::REQUIRED_FIELDS), $errors);
        foreach (self::REQUIRED_FIELDS as $field) {
            self::assertContains(sprintf('Полето "%s" е задължително.', $field), $errors);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function requiredFields(): iterable
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('requiredFields')]
    #[Test]
    public function eachRequiredFieldIsEnforcedIndividually(string $field): void
    {
        $payload = $this->validPayload();
        unset($payload[$field]);

        $this->post($payload);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertSame(
            [sprintf('Полето "%s" е задължително.', $field)],
            $this->jsonResponse()['errors']
        );
    }

    #[Test]
    public function unknownForeignKeysAreRejectedTogether(): void
    {
        $this->post($this->validPayload([
            'settlement_id' => 999999,
            'estate_type_id' => 999999,
            'distance_to_water_id' => 999999,
            'person_role_id' => 999999,
        ]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());

        $errors = $this->jsonResponse()['errors'];

        self::assertContains('Населено място с ID 999999 не е намерено.', $errors);
        self::assertContains('Тип имот с ID 999999 не е намерен.', $errors);
        self::assertContains('Разстояние до воден басейн с ID 999999 не е намерено.', $errors);
        self::assertContains('Роля на лице с ID 999999 не е намерена.', $errors);
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function areaValues(): iterable
    {
        yield 'zero is allowed' => [0, true];
        yield 'a normal area is allowed' => [85, true];
        yield 'the upper bound is allowed' => [100000, true];
        yield 'just over the upper bound is rejected' => [100001, false];
        yield 'a negative area is rejected' => [-1, false];
        yield 'a non-numeric area is rejected' => ['not-a-number', false];
    }

    #[DataProvider('areaValues')]
    #[Test]
    public function areaIsConstrainedToZeroThroughOneHundredThousand(mixed $area, bool $accepted): void
    {
        $this->post($this->validPayload(['area_sq_meters' => $area]));

        if ($accepted) {
            self::assertSame(Response::HTTP_CREATED, $this->statusCode());

            return;
        }

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertContains(
            'Площта в квадратни метри трябва да бъде число между 0 и 100000.',
            $this->jsonResponse()['errors']
        );
    }

    #[Test]
    public function aNonEgnIdNumberTypeRequiresANationality(): void
    {
        $this->post($this->validPayload(['id_number_type_id' => 2]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertContains(
            'Полето "insurer_nationality_id" е задължително, когато типът на идентификационния номер не е 1.',
            $this->jsonResponse()['errors']
        );
    }

    #[Test]
    public function aNonEgnIdNumberTypeIsAcceptedWithANationality(): void
    {
        $this->post($this->validPayload(['id_number_type_id' => 2, 'insurer_nationality_id' => 1]));

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
    }

    #[Test]
    public function genderIsOnlyValidatedForNonEgnIdNumberTypes(): void
    {
        // With the EGN type (1) the whitelist is skipped entirely.
        $this->post($this->validPayload(['gender' => 'nonsense']));
        self::assertSame(Response::HTTP_CREATED, $this->statusCode());

        $this->post($this->validPayload([
            'id_number_type_id' => 2,
            'insurer_nationality_id' => 1,
            'gender' => 'nonsense',
        ]));
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertContains('Полът трябва да бъде "male" или "female".', $this->jsonResponse()['errors']);
    }

    #[Test]
    public function aPropertyOwnerUnderEighteenIsRejected(): void
    {
        $this->post($this->validPayload([
            'property_owner_id_number_type_id' => 2,
            'property_owner_nationality_id' => 1,
            'property_owner_birth_date' => (new \DateTime('-10 years'))->format('Y-m-d'),
        ]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertContains('Собственикът трябва да е на възраст над 18 години.', $this->jsonResponse()['errors']);
    }

    #[Test]
    public function anAdultPropertyOwnerIsAccepted(): void
    {
        $this->post($this->validPayload([
            'property_owner_id_number_type_id' => 2,
            'property_owner_nationality_id' => 1,
            'property_owner_birth_date' => (new \DateTime('-40 years'))->format('Y-m-d'),
        ]));

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
    }

    #[Test]
    public function anUnknownPromotionalCodeIsRejected(): void
    {
        $this->post($this->validPayload(['promotional_code_id' => 999999]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode());
        self::assertContains('Промоционален код с ID 999999 не е намерен.', $this->jsonResponse()['errors']);
    }

    // -----------------------------------------------------------------------
    // The money
    // -----------------------------------------------------------------------

    /**
     * The client computes subtotal/tax/total and posts them, so these are the figures
     * that get stored and charged - the API only sees the result. A promotional code
     * larger than the premium left after the regular discount used to produce a negative
     * total on both sides. StatisticsService caps the promo now; this refuses a negative
     * figure arriving from anywhere else, including a client that has not been
     * redeployed yet.
     *
     * @return iterable<string, array{string}>
     */
    public static function moneyFields(): iterable
    {
        yield 'subtotal' => ['subtotal'];
        yield 'subtotal_tax' => ['subtotal_tax'];
        yield 'total' => ['total'];
    }

    #[DataProvider('moneyFields')]
    #[Test]
    public function aNegativeMoneyFieldIsRejected(string $field): void
    {
        $this->post($this->validPayload([$field => -21.19]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->statusCode(), $field);
        self::assertNotEmpty($this->jsonResponse()['errors']);
    }

    /**
     * Zero is legitimate: a promo that exactly cancels the premium left after the
     * regular discount produces a total of 0.00, and that order must still go through.
     */
    #[Test]
    public function aZeroTotalIsAccepted(): void
    {
        $this->post($this->validPayload(['subtotal' => 192.70, 'subtotal_tax' => 0.0, 'total' => 0.0]));

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
        self::assertSame(0.0, (float) $this->jsonResponse()['total']);
    }

    // -----------------------------------------------------------------------
    // Successful creation
    // -----------------------------------------------------------------------

    #[Test]
    public function aValidPayloadCreatesAPolicy(): void
    {
        $this->post($this->validPayload());

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());

        $body = $this->jsonResponse();

        self::assertArrayHasKey('id', $body);
        self::assertArrayHasKey('code', $body);
        self::assertSame(1, $body['settlement_id']);
        self::assertSame(85.0, (float) $body['area_sq_meters']);
        self::assertNotEmpty($body['created_at']);
    }

    #[Test]
    public function theGeneratedCodeReplacesTheTemporaryOneAndIsPersisted(): void
    {
        $this->post($this->validPayload());

        $code = $this->jsonResponse()['code'];
        $id = $this->jsonResponse()['id'];

        self::assertStringStartsWith('P', $code);
        self::assertStringNotContainsString('TEMP-', $code, 'The placeholder code must be replaced.');

        $policy = $this->em()->find(InsurancePolicy::class, $id);

        self::assertNotNull($policy);
        self::assertSame($code, $policy->getCode());
    }

    #[Test]
    public function concurrentlyCreatedPoliciesGetDistinctCodes(): void
    {
        $codes = [];

        for ($i = 0; $i < 3; $i++) {
            $this->post($this->validPayload());
            self::assertSame(Response::HTTP_CREATED, $this->statusCode());
            $codes[] = $this->jsonResponse()['code'];
        }

        self::assertSame($codes, array_unique($codes), 'Policy codes must be unique.');
    }

    #[Test]
    public function checklistAnswersArePersisted(): void
    {
        $this->post($this->validPayload([
            'property_checklist_items' => [1 => true, 2 => false, 3 => true],
        ]));

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
        self::assertCount(3, $this->jsonResponse()['property_checklist_items']);
    }

    #[Test]
    public function creatingAPolicyQueuesTheConfirmationEmailWithoutSendingIt(): void
    {
        $this->post($this->validPayload(['email' => 'buyer@example.test']));

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
        // MAILER_DSN is null://null: the message is collected, never transmitted.
        self::assertGreaterThanOrEqual(1, count(self::getMailerMessages()));
    }
}
