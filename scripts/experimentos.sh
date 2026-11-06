#!/bin/sh
# Reproduces experiments A-E between two containers (client -> server) on a Docker network.
# Network conditions come from Linux tc netem on the client's interface (egress only).
# Usage: scripts/experimentos.sh [output dir]      Needs: Docker. Takes about 6 minutes.
set -e
OUT="${1:-$(pwd)/docs/experimentos/dados}"
IMAGE=pulsemonitor
NET=pulse-lab
mkdir -p "$OUT"
KEY=base64:cHVsc2Vtb25pdG9yLWRlbW8ta2V5LWZvci1sYWJzLTE=
ENVS="-e APP_ENV=local -e APP_DEBUG=false -e APP_KEY=$KEY -e APP_URL=http://lab-server:8000 -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/lab.sqlite -e SESSION_DRIVER=array -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null -e LOG_CHANNEL=stderr -e RUN_MIGRATIONS=1"

cleanup() { docker rm -f lab-server lab-client >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT
cleanup
docker image inspect "$IMAGE" >/dev/null 2>&1 || docker build -t "$IMAGE" .
docker network create "$NET" >/dev/null

docker run -d --name lab-server --network "$NET" $ENVS -e PHP_CLI_SERVER_WORKERS=4 "$IMAGE" \
    sh -c 'php artisan lab:socket-server --port=9601 > /dev/null 2>&1 & exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload' >/dev/null
docker run -d --name lab-client --network "$NET" --cap-add NET_ADMIN -v "$OUT":/out $ENVS "$IMAGE" sleep infinity >/dev/null

until docker exec lab-client curl -fs http://lab-server:8000/up >/dev/null 2>&1; do sleep 1; done
sleep 3
run() { docker exec lab-client "$@"; }

{
    echo "date_utc=$(date -u +%FT%TZ)"
    docker exec lab-client uname -sr
    docker version --format 'docker {{.Server.Version}}'
    run php -v | head -1
    run ip -br addr show eth0
} > "$OUT/ambiente.txt"

measure() { # $1 = condition name
    C="$1"
    echo "=== $C: $(run tc qdisc show dev eth0 | head -1)" | tee -a "$OUT/ambiente.txt"
    run php artisan lab:run rtt --target=http://lab-server:8000/up --samples=50 --label="$C" --out=/out
    run php artisan lab:run throughput --target=http://lab-server:8000 --size=all --clients=5 --duration=5 --label="$C" --out=/out
    run php artisan lab:run concurrency --target=http://lab-server:8000/up --levels=1,2,5 --duration=5 --label="$C" --out=/out
    run php artisan lab:socket-client --host=lab-server --proto=both --mode=pingpong --count=200 --size=64 --timeout=1000 --label="$C" --out=/out | tail -6
    run php artisan lab:socket-client --host=lab-server --proto=both --mode=burst --count=2000 --size=512 --timeout=5000 --label="$C" --out=/out | tail -6
}

measure base
# limit: the default queue of 1000 packets would overflow on a burst of 2000 and hide the configured loss
NETEM="limit 100000"
run tc qdisc add dev eth0 root netem $NETEM delay 50ms 10ms
measure atraso50ms
run tc qdisc change dev eth0 root netem $NETEM loss 5%
measure perda5pct
run tc qdisc change dev eth0 root netem $NETEM delay 50ms 10ms loss 5%
measure atraso50ms_perda5pct
run tc qdisc del dev eth0 root
echo "Results in $OUT"
