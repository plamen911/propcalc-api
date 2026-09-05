<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preset selection by property area.
 *
 * This does NOT live in TariffPresetService - it is a filter in
 * Admin\TariffPresetController::listTariffPresets, which keeps presets whose FIRST
 * clause has a sum insured of at least area * 1000, then caps the result at 5.
 *
 * With the seeded data the first clause of each preset is the building sum insured:
 * preset 1 = 120000, preset 2 = 150000, preset 3 = 320000, preset 4 = 400000.
 */
final class TariffPresetAreaFilterTest extends ApiTestCase
{
    private function listPresets(?int $area): array
    {
        $uri = '/api/v1/insurance-policies/admin/tariff-presets';
        if ($area !== null) {
            $uri .= '?area_sq_meters=' . $area;
        }

        $this->request('GET', $uri, null, $this->adminToken());

        self::assertSame(Response::HTTP_OK, $this->statusCode());

        return $this->jsonResponse();
    }

    #[Test]
    public function withoutAnAreaEveryPresetIsReturned(): void
    {
        self::assertCount(4, $this->listPresets(null));
    }

    #[Test]
    public function anAreaOfZeroDisablesTheFilter(): void
    {
        // The controller only filters when area_sq_meters > 0.
        self::assertCount(4, $this->listPresets(0));
    }

    /** @return iterable<string, array{int, list<string>}> */
    public static function areaCases(): iterable
    {
        yield 'exactly on the preset 1 boundary is kept (comparison is >=)' => [120, ['Пакет 1', 'Пакет 2', 'Пакет 3', 'Пакет 4']];
        yield 'one square metre over drops preset 1' => [121, ['Пакет 2', 'Пакет 3', 'Пакет 4']];
        yield 'exactly on the preset 2 boundary keeps it' => [150, ['Пакет 2', 'Пакет 3', 'Пакет 4']];
        yield 'one over drops preset 2' => [151, ['Пакет 3', 'Пакет 4']];
        yield 'exactly on the preset 3 boundary keeps it' => [320, ['Пакет 3', 'Пакет 4']];
        yield 'one over drops preset 3' => [321, ['Пакет 4']];
        yield 'exactly on the preset 4 boundary keeps it' => [400, ['Пакет 4']];
        yield 'beyond the largest preset returns nothing' => [401, []];
    }

    #[DataProvider('areaCases')]
    #[Test]
    public function presetsAreFilteredByAreaTimesOneThousand(int $area, array $expectedNames): void
    {
        self::assertSame($expectedNames, array_column($this->listPresets($area), 'name'));
    }

    #[Test]
    public function aVeryLargeAreaExcludesEverything(): void
    {
        self::assertSame([], $this->listPresets(100000));
    }

    #[Test]
    public function theFilteredListIsReindexedAsAJsonArray(): void
    {
        $body = $this->listPresets(321);

        // array_values() is applied, so the response is a JSON array, not an object.
        self::assertSame([0], array_keys($body));
    }

    #[Test]
    public function filteringPreservesTheFullPresetPayload(): void
    {
        $preset = $this->listPresets(400)[0];

        self::assertSame('Пакет 4', $preset['name']);
        self::assertArrayHasKey('statistics', $preset);
        self::assertArrayHasKey('tariff_preset_clauses', $preset);
        self::assertCount(15, $preset['tariff_preset_clauses']);
    }
}
