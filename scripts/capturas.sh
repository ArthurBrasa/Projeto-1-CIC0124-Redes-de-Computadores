#!/bin/sh
# Traffic captures on the Docker network (see docs/capturas/README.md). Uses only public demo tokens.
# Usage: scripts/capturas.sh [output dir]      Needs: Docker and the pulsemonitor image.
set -e
OUT="${1:-$(pwd)/docs/capturas}"
NET=pulse-cap
mkdir -p "$OUT"
KEY=base64:cHVsc2Vtb25pdG9yLWRlbW8ta2V5LWZvci1sYWJzLTE=
ENVS="-e APP_ENV=local -e APP_DEBUG=false -e APP_KEY=$KEY -e APP_URL=http://cap-server:8000 -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/lab.sqlite -e SESSION_DRIVER=array -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null -e LOG_CHANNEL=stderr -e RUN_MIGRATIONS=1"
cleanup() { docker rm -f cap-server cap-client >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT
cleanup
docker network create "$NET" >/dev/null
docker run -d --name cap-server --network "$NET" --cap-add NET_ADMIN --cap-add NET_RAW -v "$OUT":/out $ENVS -e PHP_CLI_SERVER_WORKERS=4 pulsemonitor \
    sh -c 'php artisan lab:socket-server --port=9601 > /dev/null 2>&1 & exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload' >/dev/null
docker run -d --name cap-client --network "$NET" --cap-add NET_ADMIN --cap-add NET_RAW -v "$OUT":/out $ENVS pulsemonitor sleep infinity >/dev/null
until docker exec cap-client curl -fs http://cap-server:8000/up >/dev/null 2>&1; do sleep 1; done
sleep 3

start() { # $1 container, $2 file, $3 filter
    docker exec -d "$1" sh -c "tcpdump -i eth0 -s 0 -U -w /out/$2 $3 >/dev/null 2>&1"
    sleep 2
}
stop() { sleep 2; docker exec cap-client sh -c 'pkill tcpdump || true' 2>/dev/null || true; docker exec cap-server sh -c 'pkill tcpdump || true' 2>/dev/null || true; sleep 1; }
hook() { # $1 app, $2 token, $3 body
    docker exec cap-client curl -s --http1.1 -o /dev/null -w "HTTP %{http_code}\n" -X POST "http://cap-server:8000/api/v1/hooks/$1" \
        -H "Authorization: Bearer $2" -H 'Content-Type: application/json' -d "$3"
}
A=app_sensorlaba; TA=pm_demo_sensor_a_0000000000000000000000000
B=app_sensorlabb; TB=pm_demo_sensor_b_0000000000000000000000000

# 1) Webhook over HTTP: two clients at the same time, then each error case
start cap-client captura1-webhook.pcap 'tcp port 8000'
hook $A $TA '{"level":"info","message":"Temperature reading","source":"sensor-a","payload":{"temperature_c":23.4}}' &
hook $B $TB '{"level":"error","message":"Sensor offline","source":"sensor-b","payload":{"code":"E42"}}' &
wait
hook $A wrong-token '{"level":"info","message":"x"}'
hook $A $TA '{"level":"info", message}'
hook $A $TA '{"level":"fatal"}'
stop

# 3) Same load over TCP and UDP
start cap-client captura3-tcp-udp.pcap 'port 9601'
docker exec cap-client php artisan lab:socket-client --host=cap-server --proto=both --mode=pingpong --count=5 --size=64 --label=captura3 --out=/tmp | grep -E '^\| (tcp|udp)'
stop

# 4) 50 ms +- 10 ms and 5% loss on the SERVER's egress, captured on both ends.
# On Linux tcpdump sees outgoing packets after the qdisc, so netem must be on the far side
# of the capture point: otherwise the delay and the dropped packets would be invisible.
docker exec cap-server tc qdisc add dev eth0 root netem limit 100000 delay 50ms 10ms loss 5%
start cap-client captura4-perda-cliente.pcap 'port 9601 or tcp port 8000'
start cap-server captura4-perda-servidor.pcap 'port 9601 or tcp port 8000'
docker exec cap-client php artisan lab:run rtt --target=http://cap-server:8000/up --samples=30 --label=captura4 --out=/out | tail -2 | head -1
docker exec cap-client php artisan lab:socket-client --host=cap-server --proto=both --mode=burst --count=500 --size=512 --timeout=5000 --label=captura4 --out=/out | grep -E '^\| (tcp|udp)'
stop
ls -l "$OUT"
