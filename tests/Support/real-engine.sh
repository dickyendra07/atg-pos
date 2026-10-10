#!/usr/bin/env bash
#
# Runs the real-engine tests (separate PHP processes, real row locks) against a BRAND-NEW disposable MySQL database.
#
#   tests/Support/real-engine.sh                       # the five real-engine test files
#   tests/Support/real-engine.sh --filter scenario_a   # any phpunit arguments
#
# What it does: creates atg_void_test_<random> on a LOCAL server, runs phpunit against only that database with the two
# explicit opt-in flags, and drops it again on exit. It never touches any other database. The harness (RealEngineGuard)
# re-checks all of this and refuses anything else, so this script is a convenience, not the safety net.
#
# Server: ATG_RE_HOST (127.0.0.1, localhost or ::1 only), ATG_RE_PORT (3306), ATG_RE_USER (root), password via MYSQL_PWD.
# Needs the mysql client on PATH, APP_KEY in the environment or .env, and a user allowed to CREATE/DROP DATABASE.

set -euo pipefail

HOST="${ATG_RE_HOST:-127.0.0.1}"
PORT="${ATG_RE_PORT:-3306}"
DB_USER="${ATG_RE_USER:-root}"

case "$HOST" in
    127.0.0.1 | localhost | ::1) ;;
    *)
        echo "Refusing: '$HOST' is not a local test host (127.0.0.1, localhost, ::1)." >&2
        exit 2
        ;;
esac

DB="atg_void_test_$(openssl rand -hex 6)"

if ! [[ "$DB" =~ ^atg_void_test_[a-z0-9]{6,40}$ ]]; then
    echo "Refusing: generated database name '$DB' does not match the disposable pattern." >&2
    exit 2
fi

MYSQL=(mysql --no-defaults -h"$HOST" -P"$PORT" -u"$DB_USER")

"${MYSQL[@]}" -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4"
trap '"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB\`"' EXIT
echo "Disposable database: $DB on $HOST:$PORT"

if [ "$#" -eq 0 ]; then
    set -- tests/Feature/RealEngineWriterLockTest.php tests/Feature/StockAdjustmentVoidRealEngineTest.php tests/Feature/StockAdjustmentVoidDecimalRealEngineTest.php tests/Feature/RealEngineTransferConcurrencyTest.php tests/Feature/RealEngineTransferIdempotencyTest.php
fi

APP_ENV=testing \
ATG_REAL_ENGINE_TESTS=1 \
ATG_REAL_ENGINE_DESTRUCTIVE_SETUP=wipe-disposable-database \
DB_CONNECTION=mysql DB_HOST="$HOST" DB_PORT="$PORT" DB_USERNAME="$DB_USER" DB_PASSWORD="${MYSQL_PWD:-}" DB_DATABASE="$DB" DB_URL= \
    php vendor/bin/phpunit "$@"
