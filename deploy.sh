#!/usr/bin/env bash
#
# deploy.sh — deploy this API to the icnhost production host over rsync/SSH.
#
# Replaces the old manual FTP upload. rsync is delta-based and atomic per file,
# and this script always shows you an itemised preview of exactly what would
# change before anything is written.
#
# The artifact is built from a clean `git archive` export, NOT from the working
# tree, so only tracked files can ever ship: vendor/, var/, .env, config/jwt/*.pem,
# .idea/ and myreadme.md are excluded by construction rather than by a list that
# can drift. Everything else that must not be touched — server-owned cPanel files,
# tracked dev-only files — lives in ./deploy-excludes.txt.
#
# Dependencies are installed ON THE SERVER (composer.phar must be there);
# vendor/ is never uploaded. The server also owns its .env(.local) and JWT keys.
#
# Requires: SSH key access to the prod host.
#
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---------------------------------------------------------------- configuration
DEPLOY_SSH_HOST="${DEPLOY_SSH_HOST:-91.215.216.12}"
DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-22022}"
DEPLOY_SSH_USER="${DEPLOY_SSH_USER:-teodor81}"
DEPLOY_PATH="${DEPLOY_PATH:-/home/teodor81/propcalc.zastrahovaite.com}"
DEPLOY_PHP="${DEPLOY_PHP:-/usr/local/php8.4/bin/php}"

EXCLUDE_FILE="deploy-excludes.txt"

REF="HEAD"
DRY_RUN=0
ASSUME_YES=0
RUN_MIGRATE=1

usage() {
    cat <<'USAGE'
Usage: ./deploy.sh [options]

Deploys the API to production over rsync/SSH, then installs dependencies, runs
migrations and rebuilds the cache on the server.

Options:
      --ref <git-ref>  Commit/branch/tag to deploy (default: HEAD).
  -n, --dry-run        Show what would change, then stop. Writes nothing.
  -y, --yes            Skip the confirmation prompt.
      --no-migrate     Skip `doctrine:migrations:migrate` on the server.
  -h, --help           Show this help.

Environment overrides:
  DEPLOY_SSH_HOST (default 91.215.216.12)   DEPLOY_SSH_PORT (22022)
  DEPLOY_SSH_USER (teodor81)                DEPLOY_PATH (/home/teodor81/propcalc.zastrahovaite.com)
  DEPLOY_PHP (/usr/local/php8.4/bin/php)

Notes:
  rsync runs WITHOUT --delete: files are added and updated, never removed.
  Rolling back with --ref <older-tag> therefore restores changed files but does
  not delete files that only existed in the newer release.
USAGE
}

while [ $# -gt 0 ]; do
    case "$1" in
        --ref)         shift; [ $# -gt 0 ] || { echo "--ref needs a value" >&2; exit 2; }; REF="$1" ;;
        --ref=*)       REF="${1#*=}" ;;
        -n|--dry-run)  DRY_RUN=1 ;;
        -y|--yes)      ASSUME_YES=1 ;;
        --no-migrate)  RUN_MIGRATE=0 ;;
        -h|--help)     usage; exit 0 ;;
        *)             echo "Unknown option: $1" >&2; echo >&2; usage >&2; exit 2 ;;
    esac
    shift
done

# ---------------------------------------------------------------------- helpers
step() { printf '\n\033[1;34m==>\033[0m \033[1m%s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33mWARNING:\033[0m %s\n' "$*" >&2; }
die()  { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

SSH_OPTS=(-p "$DEPLOY_SSH_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
REMOTE="${DEPLOY_SSH_USER}@${DEPLOY_SSH_HOST}"

remote() { ssh "${SSH_OPTS[@]}" "$REMOTE" "$@"; }

STAGE=""

cleanup() {
    [ -n "$STAGE" ] && rm -f "${STAGE}.preview"
    [ -n "$STAGE" ] && [ -d "$STAGE" ] && rm -rf "$STAGE"
    return 0
}
trap cleanup EXIT

# --------------------------------------------------------------------- preflight
step "Preflight checks"

command -v git   >/dev/null 2>&1 || die "git is not installed or not on PATH."
command -v rsync >/dev/null 2>&1 || die "rsync is not installed or not on PATH."
[ -f "$EXCLUDE_FILE" ] || die "Missing ${EXCLUDE_FILE} — it must sit next to deploy.sh."
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "Not inside a git work tree."

REF_SHA="$(git rev-parse --short "$REF" 2>/dev/null)" || die "Unknown git ref: ${REF}"
REF_SUBJECT="$(git log -1 --format=%s "$REF_SHA")"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
info "Deploying ref '${REF}' -> ${REF_SHA} (branch ${BRANCH})"
info "  ${REF_SUBJECT}"

# The artifact comes from `git archive`, so the working tree cannot leak into it —
# but uncommitted work silently NOT shipping is worth a heads-up.
if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Working tree has uncommitted changes. They will NOT be deployed — only ${REF_SHA} ships."
fi

# BatchMode so a missing/expired key fails fast instead of hanging on a prompt.
remote true >/dev/null 2>&1 \
    || die "Cannot reach ${REMOTE}:${DEPLOY_SSH_PORT} with key auth.
    Check the host is up and your SSH key is in its authorized_keys."
info "SSH to ${REMOTE}:${DEPLOY_SSH_PORT} OK (key auth)."

remote "[ -d '$DEPLOY_PATH' ]" 2>/dev/null || die "Remote path does not exist: ${DEPLOY_PATH}"
remote "[ -x '$DEPLOY_PHP' ]"  2>/dev/null || die "Remote PHP not found/executable: ${DEPLOY_PHP}"
remote "[ -f '$DEPLOY_PATH/composer.phar' ]" 2>/dev/null \
    || die "composer.phar not found in ${DEPLOY_PATH}.
    vendor/ is never uploaded, so the server needs composer.phar to install deps."
remote "command -v rsync" >/dev/null 2>&1 || die "rsync is not available on the remote host."
# Server-owned config: never shipped, so it must already be in place.
remote "[ -f '$DEPLOY_PATH/.env.local' ] || [ -f '$DEPLOY_PATH/.env.local.php' ] || [ -f '$DEPLOY_PATH/.env' ]" 2>/dev/null \
    || die "No .env, .env.local or .env.local.php in ${DEPLOY_PATH}.
    Env files are never uploaded; create the production config on the server first."
remote "[ -f '$DEPLOY_PATH/config/jwt/private.pem' ] && [ -f '$DEPLOY_PATH/config/jwt/public.pem' ]" 2>/dev/null \
    || die "JWT keys missing in ${DEPLOY_PATH}/config/jwt/.
    Keys are never uploaded; generate them on the server (see JWT_KEYS_SETUP.md)."
info "Remote OK: ${DEPLOY_PATH}, PHP $(remote "$DEPLOY_PHP -r 'echo PHP_VERSION;'" 2>/dev/null), composer.phar, env and JWT keys present."

# rsync never deletes, so a migration removed from the repo lingers on the server
# and doctrine:migrations:migrate would still try to run it. Refuse up front.
REF_MIGRATIONS="$(git ls-tree --name-only "$REF_SHA" migrations/ | grep -E '\.php$' | xargs -n1 basename | sort)"
REMOTE_MIGRATIONS="$(remote "cd '$DEPLOY_PATH/migrations' 2>/dev/null && ls -1 *.php 2>/dev/null" 2>/dev/null | sort || true)"
STALE_MIGRATIONS="$(comm -13 <(printf '%s\n' "$REF_MIGRATIONS") <(printf '%s\n' "$REMOTE_MIGRATIONS") | grep . || true)"
if [ -n "$STALE_MIGRATIONS" ]; then
    MSG="Server has migration files that ${REF_SHA} does not:
$(printf '%s\n' "$STALE_MIGRATIONS" | sed 's/^/      /')
    Move them out of ${DEPLOY_PATH}/migrations/ (and, if already applied, drop them
    from the version table with doctrine:migrations:version --delete)."
    if [ "$RUN_MIGRATE" -eq 1 ]; then
        die "$MSG
    Or pass --no-migrate to deploy without running migrations."
    fi
    warn "$MSG"
fi

# ------------------------------------------------------------------------ stage
step "Staging a clean export of ${REF_SHA}"

# mktemp, deliberately outside the repo: the export is disposable and must never
# be able to clobber the working tree.
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/deploy-stage.XXXXXX")"
git archive "$REF_SHA" | tar -x -C "$STAGE"
info "Exported $(find "$STAGE" -type f | wc -l | tr -d ' ') tracked files to the staging dir."

# --------------------------------------------------------------------- transfer
# --checksum, not the default size+mtime heuristic: `git archive` stamps every
# exported file with the commit timestamp, so mtimes differ on every deploy and
# rsync would re-upload the whole tree each time. Comparing content hashes keeps
# the preview honest (only real changes listed) and the transfer incremental.
# The tree is small (no vendor/, no var/), so hashing is cheap.
RSYNC_OPTS=(
    -az --checksum --no-perms --no-owner --no-group --no-times --omit-dir-times
    --human-readable --itemize-changes
    --exclude-from="$EXCLUDE_FILE"
    -e "ssh -p ${DEPLOY_SSH_PORT} -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o LogLevel=ERROR"
)
# No --delete, ever: add/update only, so server runtime files survive.
TARGET="${REMOTE}:${DEPLOY_PATH}/"

step "Preview — what would change on production"
info "(dry run, nothing is written)"

PREVIEW="$STAGE.preview"
rsync "${RSYNC_OPTS[@]}" --dry-run "$STAGE/" "$TARGET" >"$PREVIEW" 2>/dev/null \
    || { cat "$PREVIEW" >&2; die "rsync dry run failed."; }

# Itemised codes: column 1 is '<' for a file being sent, 'c' for a dir/symlink
# being created. Everything else is an already-identical file we can stay quiet
# about — with --checksum, "identical" means identical content.
CHANGES="$(grep -E '^[<c>]' "$PREVIEW" || true)"
CHANGE_COUNT="$(printf '%s' "$CHANGES" | grep -c . || true)"
rm -f "$PREVIEW"

if [ "$CHANGE_COUNT" -eq 0 ]; then
    info "No changes — production already matches ${REF_SHA}."
else
    echo
    printf '%s\n' "$CHANGES" | sed 's/^/    /'
    echo
    info "${CHANGE_COUNT} file(s)/dir(s) would be created or updated. Nothing is ever deleted."
fi

if [ "$DRY_RUN" -eq 1 ]; then
    info "Dry run only (-n). Nothing was changed."
    exit 0
fi

if [ "$CHANGE_COUNT" -eq 0 ] && [ "$RUN_MIGRATE" -eq 0 ]; then
    info "Nothing to do."
    exit 0
fi

if [ "$ASSUME_YES" -eq 0 ]; then
    printf '\n\033[1;33mDeploy %s to %s:%s ?\033[0m\n' "$REF_SHA" "$REMOTE" "$DEPLOY_PATH"
    printf 'Type "yes" to continue: '
    read -r REPLY
    [ "$REPLY" = "yes" ] || die "Aborted."
fi

step "Transferring files"
rsync "${RSYNC_OPTS[@]}" "$STAGE/" "$TARGET" || die "rsync transfer failed."

# ------------------------------------------------------------------ post-deploy
step "Running post-deploy steps on the server"

# APP_ENV is forced to prod as a real env var (which beats any .env file): with
# --no-dev the dev-only bundles are gone, so composer's auto-scripts must not boot
# the kernel in dev. Those auto-scripts already run cache:clear (which warms the
# cache) and assets:install, so no separate cache step is needed.
POST="cd '$DEPLOY_PATH' && export APP_ENV=prod APP_DEBUG=0"
POST="$POST && $DEPLOY_PHP composer.phar install --no-dev --optimize-autoloader --no-interaction --no-progress"
[ "$RUN_MIGRATE" -eq 1 ] && POST="$POST && $DEPLOY_PHP bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration"

remote "$POST" || die "Post-deploy steps failed on the server. The files are uploaded;
    fix the cause and re-run, or investigate with:
    ssh -p ${DEPLOY_SSH_PORT} ${REMOTE}"

step "Done"
info "Deployed ${REF_SHA} (${REF_SUBJECT}) to ${DEPLOY_PATH}."
info "Verify: curl -sI https://propcalc.zastrahovaite.com/ | head -1"
