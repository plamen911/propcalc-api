# Test suite

## Running it

```bash
composer install
composer test:setup     # once: throwaway JWT keys + test database
composer test           # the whole suite
```

`composer test:setup` writes `.env.test.local` with a DSN derived from your existing
`.env`, pointing at a separate `propcalc_test` database. Both that file and the generated
keys in `var/jwt/` are gitignored.

Individual suites and filters:

```bash
vendor/bin/phpunit --testsuite Unit          # no kernel, no database
vendor/bin/phpunit --testsuite Integration   # kernel + seeded test database
vendor/bin/phpunit --testsuite Api           # full HTTP requests
vendor/bin/phpunit --filter TariffPreset
TEST_DB_REBUILD=1 vendor/bin/phpunit         # force a schema rebuild + reseed
```

## How it is wired

`tests/bootstrap.php` drops and recreates the test schema and runs `app:seed-all` **once
per run**, and only when something that shapes fixture data has changed (entities,
commands, the settlement spreadsheet). It refuses to touch any database whose name does
not end in `_test`.

Each individual test then runs inside a transaction that is rolled back afterwards
(`dama/doctrine-test-bundle`), so tests share the seeded reference data but never see one
another's writes.

## Safety rails

`tests/Integration/EnvironmentGuardTest.php` asserts these, so they cannot regress:

* the connection points at a `_test` database;
* `MAILER_DSN` is `null://null`, so no mail is ever transmitted;
* JWT signing uses the throwaway keys in `var/jwt/`, never `config/jwt/`;
* outbound HTTP is blocked. `tests/Support/NoNetworkStreamWrapper.php` replaces the
  `http`/`https` stream wrappers: image URLs get a local 1x1 JPEG (this is what lets
  `PdfService` render its hardcoded remote logo offline) and anything else throws.

## Tests named `*_KNOWN_GAP`

These assert what the application currently does, not what it arguably should do. They
document real defects that were deliberately left unfixed, so that the suite stays green
and any change in that behaviour is caught. `RouteSmokeTest::KNOWN_SERVER_ERRORS` works
the same way for routes that currently return a 500 — fixing one makes that test fail,
which is the prompt to remove it from the list.
