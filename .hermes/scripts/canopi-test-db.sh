#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$ROOT/.hermes/test-db.env"
CONTAINER="canopi-mariadb-test"
IMAGE="mariadb:10.11"
HOST="127.0.0.1"
PORT="3307"

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

load_env() {
    [[ -f "$ENV_FILE" ]] || fail "$ENV_FILE tidak ada"
    [[ "$(stat -c '%a' "$ENV_FILE")" == "600" ]] || fail "mode credential wajib 600"
    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a

    [[ "${MARIADB_DATABASE:-}" == "canopi_test" ]] || fail "database wajib canopi_test"
    [[ "${MARIADB_USER:-}" == "canopi_test" ]] || fail "user wajib canopi_test"
    [[ -n "${MARIADB_PASSWORD:-}" ]] || fail "password user kosong"
    [[ -n "${MARIADB_ROOT_PASSWORD:-}" ]] || fail "password root kosong"
    [[ -n "${CANOPI_TEST_APP_KEY:-}" ]] || fail "APP_KEY test kosong"
}

assert_resources() {
    python3 - <<'PY'
from pathlib import Path
mem = {}
for line in Path('/proc/meminfo').read_text().splitlines():
    key, value = line.split(':', 1)
    mem[key] = int(value.strip().split()[0])
available = mem['MemAvailable'] / 1024 / 1024
print(f'MEM_AVAILABLE_GIB={available:.3f}')
if available < 1.3:
    raise SystemExit('FAIL: memory available kurang dari 1,3 GiB')
PY

    if ss -H -ltn '( sport = :3307 )' | python3 -c 'import sys; raise SystemExit(0 if sys.stdin.read().strip() else 1)'; then
        fail "port 3307 sedang dipakai"
    fi

    local health
    health="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:5678/healthz || true)"
    [[ "$health" == "200" ]] || fail "n8n tidak sehat sebelum DB start (HTTP $health)"
}

export_laravel_env() {
    [[ "$HOST" == "127.0.0.1" ]] || fail "DB_HOST tidak aman"
    [[ "$PORT" == "3307" ]] || fail "DB_PORT tidak aman"
    [[ "$MARIADB_DATABASE" == "canopi_test" ]] || fail "DB_DATABASE tidak aman"

    export APP_ENV=testing
    export APP_DEBUG=false
    export APP_KEY="$CANOPI_TEST_APP_KEY"
    export APP_URL=http://localhost
    export DB_CONNECTION=mysql
    export DB_HOST="$HOST"
    export DB_PORT="$PORT"
    export DB_DATABASE="$MARIADB_DATABASE"
    export DB_USERNAME="$MARIADB_USER"
    export DB_PASSWORD="$MARIADB_PASSWORD"
    export DB_URL=
    export CACHE_STORE=array
    export SESSION_DRIVER=array
    export QUEUE_CONNECTION=sync
    export MAIL_MAILER=array
    export BROADCAST_CONNECTION=null
    export TELEGRAM_KARYAWAN_TOKEN=
    export TELEGRAM_OWNER_TOKEN=
}

start_db() {
    load_env
    if docker container inspect "$CONTAINER" >/dev/null 2>&1; then
        fail "container $CONTAINER sudah ada; gunakan status/stop"
    fi
    assert_resources

    docker image inspect "$IMAGE" >/dev/null 2>&1 || docker pull "$IMAGE"

    docker run --rm -d \
        --name "$CONTAINER" \
        --memory=384m \
        --memory-swap=384m \
        --cpus=0.35 \
        --tmpfs /var/lib/mysql:rw,noexec,nosuid,size=536870912 \
        --publish 127.0.0.1:3307:3306 \
        --env-file "$ENV_FILE" \
        --health-cmd='healthcheck.sh --connect --innodb_initialized' \
        --health-interval=2s \
        --health-timeout=2s \
        --health-retries=45 \
        --health-start-period=10s \
        "$IMAGE" \
        --innodb-buffer-pool-size=128M \
        --max-connections=10 \
        --performance-schema=OFF \
        --skip-name-resolve >/dev/null

    local status=""
    for _ in $(seq 1 60); do
        status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$CONTAINER" 2>/dev/null || true)"
        if [[ "$status" == "healthy" ]]; then
            printf 'PASS: %s healthy\n' "$CONTAINER"
            return 0
        fi
        if [[ "$status" == "unhealthy" ]]; then
            docker logs --tail 50 "$CONTAINER" >&2 || true
            docker stop -t 10 "$CONTAINER" >/dev/null 2>&1 || true
            fail "container unhealthy"
        fi
        sleep 2
    done

    docker logs --tail 50 "$CONTAINER" >&2 || true
    docker stop -t 10 "$CONTAINER" >/dev/null 2>&1 || true
    fail "timeout menunggu MariaDB healthy (status terakhir: $status)"
}

status_db() {
    if ! docker container inspect "$CONTAINER" >/dev/null 2>&1; then
        printf 'STATUS=stopped\n'
        return 0
    fi
    docker inspect --format 'STATUS={{.State.Status}} HEALTH={{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} MEMORY={{.HostConfig.Memory}} NANOS={{.HostConfig.NanoCpus}} RESTART={{.HostConfig.RestartPolicy.Name}}' "$CONTAINER"
    docker stats --no-stream --format 'CPU={{.CPUPerc}} MEM={{.MemUsage}} MEM_PCT={{.MemPerc}} PIDS={{.PIDs}}' "$CONTAINER"
}

stop_db() {
    if docker container inspect "$CONTAINER" >/dev/null 2>&1; then
        docker stop -t 10 "$CONTAINER" >/dev/null
    fi
    for _ in $(seq 1 40); do
        if ! docker container inspect "$CONTAINER" >/dev/null 2>&1; then
            printf 'PASS: %s stopped and removed\n' "$CONTAINER"
            return 0
        fi
        sleep 0.25
    done
    fail "container masih ada 10 detik setelah stop"
}

command="${1:-}"
case "$command" in
    start)
        start_db
        ;;
    status)
        status_db
        ;;
    pdo-smoke)
        load_env
        export_laravel_env
        php "$ROOT/.hermes/scripts/canopi-pdo-smoke.php"
        ;;
    artisan)
        shift
        load_env
        export_laravel_env
        exec php "$ROOT/artisan" "$@"
        ;;
    stop)
        stop_db
        ;;
    *)
        printf 'Usage: %s {start|status|pdo-smoke|artisan <args...>|stop}\n' "$0" >&2
        exit 2
        ;;
esac
