<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/insurance-policies/admin/tariff-presets.
 *
 * The create action used to populate the entity straight from the decoded body and save
 * it: a missing name reached the NOT NULL column and answered 500 (pinned in
 * RouteSmokeTest::KNOWN_SERVER_ERRORS), and a non-string name died on setName()'s type
 * declaration. Both are client mistakes and answer 422 with field-keyed errors now.
 */
final class TariffPresetCreateValidationTest extends ApiTestCase
{
    private const ENDPOINT = '/api/v1/insurance-policies/admin/tariff-presets';

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'no name at all' => [[], 'name'];
        yield 'null name' => [['name' => null], 'name'];
        yield 'empty name' => [['name' => ''], 'name'];
        yield 'whitespace-only name' => [['name' => '   '], 'name'];
        yield 'numeric name' => [['name' => 42], 'name'];
        yield 'array name' => [['name' => ['a']], 'name'];
        yield 'non-boolean active' => [['name' => 'Пакет', 'active' => 'yes'], 'active'];
        yield 'clause list not an array' => [['name' => 'Пакет', 'tariff_preset_clauses' => 'x'], 'tariff_preset_clauses'];
    }

    #[DataProvider('invalidPayloads')]
    #[Test]
    public function anInvalidPayloadIsRejectedWithFieldErrors(array $payload, string $expectedField): void
    {
        $this->request('POST', self::ENDPOINT, $payload, $this->adminToken());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->statusCode());

        $body = $this->jsonResponse();

        self::assertArrayHasKey('errors', $body);
        self::assertArrayHasKey($expectedField, $body['errors'], 'The error is attributed to the offending field.');
        self::assertNotEmpty($body['errors'][$expectedField]);
        self::assertIsString($body['errors'][$expectedField][0]);
    }

    #[Test]
    public function anInvalidPayloadWritesNothing(): void
    {
        $before = $this->presetCount();

        $this->request('POST', self::ENDPOINT, [], $this->adminToken());

        self::assertSame($before, $this->presetCount(), 'A rejected create must not reach the database.');
    }

    #[Test]
    public function aValidPayloadStillCreatesThePreset(): void
    {
        $before = $this->presetCount();

        $this->request('POST', self::ENDPOINT, ['name' => 'Пакет 5'], $this->adminToken());

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
        self::assertSame('Пакет 5', $this->jsonResponse()['name']);
        self::assertSame($before + 1, $this->presetCount());
    }

    #[Test]
    public function aValidPayloadDefaultsToActive(): void
    {
        $this->request('POST', self::ENDPOINT, ['name' => 'Пакет 6'], $this->adminToken());

        self::assertTrue($this->jsonResponse()['active']);
    }

    #[Test]
    public function anExplicitInactiveFlagIsHonoured(): void
    {
        $this->request('POST', self::ENDPOINT, ['name' => 'Пакет 7', 'active' => false], $this->adminToken());

        self::assertSame(Response::HTTP_CREATED, $this->statusCode());
        self::assertFalse($this->jsonResponse()['active']);
    }

    #[Test]
    public function theNameIsTrimmed(): void
    {
        $this->request('POST', self::ENDPOINT, ['name' => '  Пакет 8  '], $this->adminToken());

        self::assertSame('Пакет 8', $this->jsonResponse()['name']);
    }

    /**
     * The endpoint is a write, so the relaxed rule that lets the public calculator read
     * the catalogue must not reach it.
     */
    #[Test]
    public function createStillRequiresAnAdminToken(): void
    {
        $this->request('POST', self::ENDPOINT, ['name' => 'Пакет'], $this->anonymousToken());

        self::assertSame(Response::HTTP_FORBIDDEN, $this->statusCode());
    }

    private function presetCount(): int
    {
        return (int) $this->em()
            ->createQuery('SELECT COUNT(p.id) FROM App\Entity\TariffPreset p')
            ->getSingleScalarResult();
    }
}
