#!/usr/bin/env bash
#
# sync-prod-db.sh — mirror the production database into the local MySQL database.
#
# Streams `mysqldump | gzip` straight off the prod server over SSH (nothing is ever
# written to the shared host), drops and recreates the local database, imports the
# dump, then runs migrations. The downloaded dump is deleted on success.
#
# No credentials live in this file: prod DB credentials are resolved from the
# server's own .env files ON the server and never cross the wire; local ones come
# from this repo's .env (DATABASE_URL), resolved the same way Symfony does.
#
# Requires: SSH key access to the prod host, local MySQL running, `mysql` on PATH.
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---------------------------------------------------------------- configuration
PROD_SSH_HOST="${PROD_SSH_HOST:-91.215.216.12}"
PROD_SSH_PORT="${PROD_SSH_PORT:-22022}"
PROD_SSH_USER="${PROD_SSH_USER:-teodor81}"
PROD_PATH="${PROD_PATH:-/home/teodor81/propcalc.zastrahovaite.com}"
PROD_PHP="${PROD_PHP:-/usr/local/php8.4/bin/php}"

ASSUME_YES=0
KEEP_DUMP=0
RUN_MIGRATE=1

usage() {
    cat <<'USAGE'
Usage: ./sync-prod-db.sh [options]

Replaces the local database with a fresh copy of production.

Options:
  -y, --yes         Skip the confirmation prompt.
      --keep-dump   Keep the downloaded dump after a successful import.
      --no-migrate  Skip `doctrine:migrations:migrate` / `cache:clear` afterwards.
  -h, --help        Show this help.

Environment overrides:
  PROD_SSH_HOST (default 91.215.216.12)   PROD_SSH_PORT (22022)
  PROD_SSH_USER (teodor81)                PROD_PATH (/home/teodor81/propcalc.zastrahovaite.com)
  PROD_PHP (/usr/local/php8.4/bin/php)

Warning: this DROPs the local database. There is no backup.
USAGE
}

while [ $# -gt 0 ]; do
    case "$1" in
        -y|--yes)     ASSUME_YES=1 ;;
        --keep-dump)  KEEP_DUMP=1 ;;
        --no-migrate) RUN_MIGRATE=0 ;;
        -h|--help)    usage; exit 0 ;;
        *)            echo "Unknown option: $1" >&2; echo >&2; usage >&2; exit 2 ;;
    esac
    shift
done

# ---------------------------------------------------------------------- helpers
step() { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
die()  { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

SUCCESS=0
DUMP_FILE=""
SSH_ERR_LOG=""

cleanup() {
    [ -n "$SSH_ERR_LOG" ] && rm -f "$SSH_ERR_LOG"

    if [ -n "$DUMP_FILE" ] && [ -f "$DUMP_FILE" ]; then
        if [ "$SUCCESS" -eq 1 ] && [ "$KEEP_DUMP" -eq 0 ]; then
            rm -f "$DUMP_FILE"
        else
            info "Dump kept at: $DUMP_FILE"
        fi
    fi
    return 0
}
trap cleanup EXIT

# --------------------------------------------------------------------- preflight
step "Preflight checks"

[ -f .env ] || die "No .env in $(pwd) — run this from the project root."
[ -f vendor/autoload.php ] || die "vendor/ is missing — run 'composer install' first."
for bin in php mysql gzip ssh; do
    command -v "$bin" >/dev/null 2>&1 || die "${bin} is not installed or not on PATH."
done

# Resolve DATABASE_URL exactly as the app does (.env, .env.local, .env.$APP_ENV…),
# then split it. urldecode matters: passwords may contain URL-escaped chars (%23).
# Values come back as shell-quoted assignments, so nothing in them is interpreted.
LOCAL_DB_VARS="$(php -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    $u = parse_url($_SERVER["DATABASE_URL"] ?? "");
    if (!$u || !isset($u["path"])) { fwrite(STDERR, "DATABASE_URL is missing or invalid\n"); exit(1); }
    foreach ([
        "DB_HOST" => $u["host"] ?? "127.0.0.1",
        "DB_PORT" => (string) ($u["port"] ?? 3306),
        "DB_USER" => urldecode($u["user"] ?? ""),
        "DB_PASS" => urldecode($u["pass"] ?? ""),
        "DB_NAME" => ltrim($u["path"], "/"),
    ] as $k => $v) { echo $k, "=", escapeshellarg($v), "\n"; }
')" || die "Could not resolve DATABASE_URL from .env."
eval "$LOCAL_DB_VARS"

[ -n "$DB_NAME" ] || die "DATABASE_URL has no database name."
[ -n "$DB_USER" ] || die "DATABASE_URL has no user."
case "$DB_HOST" in
    127.0.0.1|localhost) ;;
    *) die "Local DATABASE_URL points at '${DB_HOST}', not this machine. Refusing to drop it." ;;
esac
case "$DB_NAME" in
    *_test) die "Local database '${DB_NAME}' looks like the test database. Refusing to drop it." ;;
esac

# MYSQL_PWD keeps the password out of the process list.
local_mysql() { MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"; }

local_mysql -e 'SELECT 1' >/dev/null 2>&1 \
    || die "Cannot connect to local MySQL at ${DB_HOST}:${DB_PORT} as ${DB_USER}. Is it running?"
info "Local MySQL $(local_mysql -N -B -e 'SELECT VERSION()') at ${DB_HOST}:${DB_PORT}, database '${DB_NAME}'."

# BatchMode so a missing/expired key fails fast instead of hanging on a password prompt.
ssh -p "$PROD_SSH_PORT" -o BatchMode=yes -o ConnectTimeout=10 \
    -o StrictHostKeyChecking=accept-new \
    "${PROD_SSH_USER}@${PROD_SSH_HOST}" true >/dev/null 2>&1 \
    || die "Cannot reach ${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_SSH_PORT} with key auth.
    Check the host is up and your SSH key is in its authorized_keys."
info "SSH to ${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_SSH_PORT} OK (key auth)."

# ------------------------------------------------------------------- confirmation
if [ "$ASSUME_YES" -eq 0 ]; then
    printf '\n\033[1;33mThis will DROP the local database `%s` on %s:%s and replace it\n' \
        "$DB_NAME" "$DB_HOST" "$DB_PORT"
    printf 'with a fresh copy of production. There is no backup.\033[0m\n\n'
    printf 'Continue? [y/N] '
    read -r reply
    case "$reply" in
        [yY]|[yY][eE][sS]) ;;
        *) echo "Aborted."; exit 0 ;;
    esac
fi

# ------------------------------------------------------------ dump + download
step "Dumping production and streaming it here"
info "Nothing is written on the server — the dump is piped straight over SSH."

DUMP_FILE="$(mktemp "${TMPDIR:-/tmp}/prod-db-sync.XXXXXX")"
SSH_ERR_LOG="$(mktemp "${TMPDIR:-/tmp}/prod-db-sync.XXXXXX")"

# The remote script travels as a quoted heredoc on stdin, so nothing in it is
# expanded locally and there is no nested quoting to get wrong. Prod credentials
# are resolved by the server's own PHP/Dotenv and live only in that remote shell.
# `set -o pipefail` remotely so a mysqldump failure isn't masked by gzip's exit code.
ssh -q -p "$PROD_SSH_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new \
    "${PROD_SSH_USER}@${PROD_SSH_HOST}" \
    "bash -s -- $(printf '%q %q' "$PROD_PATH" "$PROD_PHP")" \
    > "$DUMP_FILE" 2>"$SSH_ERR_LOG" <<'REMOTE' \
    || { sed '/^$/d' "$SSH_ERR_LOG" >&2; die "Remote mysqldump failed."; }
set -euo pipefail
cd "$1"
PHP="$2"
DB_VARS="$("$PHP" -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    $u = parse_url($_SERVER["DATABASE_URL"] ?? "");
    if (!$u || !isset($u["path"])) { fwrite(STDERR, "DATABASE_URL is missing or invalid\n"); exit(1); }
    foreach ([
        "DB_HOST" => $u["host"] ?? "localhost",
        "DB_PORT" => (string) ($u["port"] ?? 3306),
        "DB_USER" => urldecode($u["user"] ?? ""),
        "DB_PASS" => urldecode($u["pass"] ?? ""),
        "DB_NAME" => ltrim($u["path"], "/"),
    ] as $k => $v) { echo $k, "=", escapeshellarg($v), "\n"; }
')"
eval "$DB_VARS"
MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
    --quick --single-transaction --no-tablespaces --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -1
REMOTE

# A truncated dump can still exit 0, so validate before touching the local database.
[ -s "$DUMP_FILE" ] || die "Downloaded dump is empty."
gzip -t "$DUMP_FILE" 2>/dev/null || die "Downloaded dump is not a valid gzip archive."
gzip -dc "$DUMP_FILE" | tail -n 5 | grep -q 'Dump completed' \
    || die "Dump looks truncated (no 'Dump completed' marker). Local database untouched."
info "Downloaded $(du -h "$DUMP_FILE" | cut -f1) — integrity verified."

# --------------------------------------------------------------------- import
step "Importing into the local database"

local_mysql -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;
                CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    || die "Could not recreate the local database."
info "Database dropped and recreated (utf8mb4 / utf8mb4_unicode_ci)."

# Prod is MariaDB 10.6, local is MySQL 8.4, so the dump is fixed up in the stream:
#   - newer MariaDB mysqldump opens with `/*!999999\- enable the sandbox mode */`,
#     which MySQL rejects as a syntax error;
#   - MyISAM tables become InnoDB, because MySQL's MyISAM caps keys at 1000 bytes.
# MariaDB-only `/*M!…*/` comments need nothing: MySQL treats them as plain comments.
gzip -dc "$DUMP_FILE" \
    | sed -e '/enable the sandbox mode/d' -e 's/^) ENGINE=MyISAM/) ENGINE=InnoDB/' \
    | local_mysql --default-character-set=utf8mb4 "$DB_NAME" \
    || die "Import failed. The dump was kept so you can retry without re-downloading."
info "Import completed."

# --------------------------------------------------------------------- verify
step "Verifying"

query() { local_mysql -N -B "$DB_NAME" -e "$1"; }

TABLE_COUNT="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}';")"
[ "${TABLE_COUNT:-0}" -gt 0 ] || die "Imported database has no tables."
POLICY_COUNT="$(query 'SELECT COUNT(*) FROM insurance_policies;')"
USER_COUNT="$(query 'SELECT COUNT(*) FROM `user`;')"
ENGINES="$(query "SELECT CONCAT(ENGINE,'=',COUNT(*)) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' GROUP BY ENGINE;" | tr '\n' ' ')"

info "Tables:             ${TABLE_COUNT}"
info "insurance_policies: ${POLICY_COUNT} rows"
info "user:               ${USER_COUNT} rows"
info "Engines:            ${ENGINES}"

# ----------------------------------------------------------------- post-import
if [ "$RUN_MIGRATE" -eq 1 ]; then
    step "Running migrations and clearing the cache"
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    php bin/console cache:clear
fi

SUCCESS=1
step "Local database is now a copy of production."
