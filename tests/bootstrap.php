<?php

declare(strict_types=1);

use App\Tests\Support\NoNetworkStreamWrapper;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

// No test may reach the outside world. Installed before the kernel boots so it
// covers every test, including PdfService's hardcoded remote logo.
NoNetworkStreamWrapper::install();

(static function (): void {
    $projectDir = dirname(__DIR__);

    $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
    if ($databaseUrl === '') {
        throw new RuntimeException('DATABASE_URL is not set for the test environment.');
    }

    // Safety rail: this bootstrap drops and recreates a schema, so it must never be
    // pointed at a database that is not obviously a throwaway test database.
    $databaseName = trim((string) (parse_url($databaseUrl, PHP_URL_PATH) ?? ''), '/');
    if (!str_ends_with($databaseName, '_test')) {
        throw new RuntimeException(sprintf(
            'Refusing to run: the test DATABASE_URL points at "%s", which does not end in "_test". '
            . 'Set a dedicated test database in .env.test.local before running the suite.',
            $databaseName
        ));
    }

    // Rebuilding the schema and running the 12 ordered seeders is slow (the settlement
    // seeder parses a 137KB spreadsheet), so it is done once per run and skipped when
    // nothing that shapes the fixture data has changed. Force with TEST_DB_REBUILD=1.
    $fingerprintSources = array_merge(
        glob($projectDir . '/src/Entity/*.php') ?: [],
        glob($projectDir . '/src/Command/*.php') ?: [],
        [$projectDir . '/specs/earthquake_zones.xlsx']
    );

    $fingerprint = '';
    foreach ($fingerprintSources as $file) {
        if (is_file($file)) {
            $fingerprint .= $file . ':' . filemtime($file) . ':' . filesize($file) . "\n";
        }
    }
    $fingerprint = hash('sha256', $fingerprint . '|' . $databaseUrl);

    $markerFile = $projectDir . '/var/cache/test/.test-db-fingerprint';
    $forced = ($_SERVER['TEST_DB_REBUILD'] ?? $_ENV['TEST_DB_REBUILD'] ?? '0') === '1';

    if (!$forced && is_file($markerFile) && trim((string) file_get_contents($markerFile)) === $fingerprint) {
        return;
    }

    $console = sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($projectDir . '/bin/console'));

    $run = static function (string $command) use ($console, $projectDir): void {
        $full = $console . ' ' . $command . ' --env=test --no-interaction 2>&1';
        exec($full, $output, $exitCode);

        if ($exitCode !== 0) {
            fwrite(STDERR, "\n[test bootstrap] Command failed: {$command}\n" . implode("\n", $output) . "\n");
            throw new RuntimeException(sprintf('Test database preparation failed at: %s', $command));
        }
    };

    fwrite(STDERR, "[test bootstrap] Preparing test database (this runs only when fixtures change)...\n");

    $run('doctrine:database:create --if-not-exists');
    $run('doctrine:schema:drop --full-database --force');
    $run('doctrine:schema:create');

    // ---------------------------------------------------------------------------
    // Compensating column defaults - TEST SCHEMA ONLY.
    //
    // Three of the twelve seeders cannot run against a schema generated from the
    // current entity mappings, because they INSERT without supplying columns that
    // the entities map as NOT NULL:
    //
    //   * app:seed-settlements       - SeedSettlementCommand never calls setNameEn(),
    //                                  setSlug(), setLat() or setLng(), but all four
    //                                  are NOT NULL => "Column 'name_en' cannot be null".
    //   * app:seed-insurance-clauses - its raw INSERT omits tariff_amount_coverage,
    //                                  which is NOT NULL with no default
    //                                  => "Field 'tariff_amount_coverage' doesn't have
    //                                  a default value".
    //   * app:seed-tariff-preset-clauses - fails as a consequence of the above, since
    //                                  insurance_clauses ends up empty (FK violation).
    //
    // app:seed-all aborts on the first failure, so on a clean database it seeds
    // nothing past estate types. These are real production bugs; they have been
    // reported and NO production code was changed to work around them.
    //
    // Adding defaults here is deliberately the smallest possible intervention: it lets
    // the REAL seeders run, so the test database holds the same reference data as
    // production (clause tariff numbers, earthquake zones, app config ids) instead of
    // hand-written approximations. The defaults only supply values the seeders forget.
    // ---------------------------------------------------------------------------
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            parse_url($databaseUrl, PHP_URL_HOST),
            parse_url($databaseUrl, PHP_URL_PORT) ?: 3306,
            $databaseName
        ),
        (string) parse_url($databaseUrl, PHP_URL_USER),
        (string) (parse_url($databaseUrl, PHP_URL_PASS) ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    foreach ([
        // SeedSettlementCommand persists a Settlement entity, so Doctrine writes an
        // explicit NULL for these four - a column DEFAULT would never be applied.
        // They have to be nullable for the seeder to run at all.
        'ALTER TABLE settlements MODIFY name_en VARCHAR(255) NULL',
        'ALTER TABLE settlements MODIFY slug VARCHAR(255) NULL',
        'ALTER TABLE settlements MODIFY lat VARCHAR(60) NULL',
        'ALTER TABLE settlements MODIFY lng VARCHAR(60) NULL',
        // SeedInsuranceClauseCommand uses raw SQL that omits this column entirely,
        // so here a DEFAULT is enough.
        'ALTER TABLE insurance_clauses ALTER COLUMN tariff_amount_coverage SET DEFAULT 0',
    ] as $sql) {
        $pdo->exec($sql);
    }

    $run('app:seed-all');

    // ---------------------------------------------------------------------------
    // Region / municipality / type rows, and the settlement links to them.
    //
    // Settlement::getFullName() dereferences getType(), getMunicipality() and
    // getRegion() with no null checks, and EmailService calls it while building the
    // order confirmation. In the production database all settlements have these three
    // foreign keys populated, so it works there - but NO seeder creates regions,
    // municipalities or types, and app:seed-settlements never links a settlement to
    // them. A freshly seeded database therefore cannot render a policy email.
    //
    // These rows restore the shape production actually has, so the tests exercise the
    // real code path rather than tripping over missing reference data.
    // ---------------------------------------------------------------------------
    $pdo->exec("INSERT INTO regions (id, name, name_en, slug) VALUES (1, 'Тестова област', 'Test Region', 'test-region')");
    $pdo->exec("INSERT INTO municipalities (id, name, name_en, slug, region_id) VALUES (1, 'Тестова община', 'Test Municipality', 'test-municipality', 1)");
    $pdo->exec("INSERT INTO types (id, name, name_en) VALUES (1, 'гр.', 'city')");
    $pdo->exec('UPDATE settlements SET region_id = 1, municipality_id = 1, type_id = 1');

    @mkdir(dirname($markerFile), 0777, true);
    file_put_contents($markerFile, $fingerprint);

    fwrite(STDERR, "[test bootstrap] Test database ready.\n");
})();
