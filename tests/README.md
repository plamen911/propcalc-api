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

When a gap is fixed, the test is flipped to assert the correct behaviour and loses the
suffix; it is never deleted.

### Fixed so far

**Authorization.** The gaps in `SecurityBoundaryTest` are closed and their tests now
assert the boundary rather than the hole: every admin route requires `ROLE_ADMIN`,
`/api/v1/tariff/pdf/email` requires a token, and the dead `^/api/v1/auth-test/public`
rule is gone. The one deliberate exception — the tariff catalogue the public calculator
reads with an anonymous token — is asserted as intended behaviour.

**Server errors.** `RouteSmokeTest::KNOWN_SERVER_ERRORS` is empty. `/policies/stats` is
no longer shadowed by `/policies/{id}` (`PolicyStatsRouteTest`) and an unvalidated
tariff-preset create answers 422 instead of dying on a NOT NULL violation
(`TariffPresetCreateValidationTest`).

**Pricing.** All three gaps are closed, to a rule chosen as a business decision:
round each line, round each step, and let the total be the printed parts added up — the
arithmetic `StatisticsService`, the client's `calc-statistics.js`, the policy PDF and the
confirmation email already shared. Both entry points round lines identically now, so a
basket prices the same whether it came from a preset or was built by hand, and a promo
code is capped at the premium left after the regular discount so no total can go
negative.

### Still open

* `AnonymousAuthTest::everyCallPersistsANewUserRow_KNOWN_GAP` — the mint path still
  inserts a row per call. The agreed mitigation is retention, not prevention:
  `app:purge-anonymous-users` ages the rows out. There is still no rate limiting.
