#!/bin/sh
set -eu
[ "$#" -eq 1 ] || { echo 'Usage: test-image.sh IMAGE' >&2; exit 1; }
image=$1
prefix="edts-image-check-$$"
network="$prefix-network"
mysql="$prefix-mysql"
app="$prefix-app"
redaction="$prefix-redaction"
environment=$(mktemp)
cleanup() {
    docker rm -fv "$app" "$mysql" "$redaction" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
    rm -f "$environment"
}
trap cleanup EXIT INT TERM
# Deliberately synthetic values for this disposable, unpublished network only.
cat > "$environment" <<'ENV'
APP_ENV=staging
APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=
APP_DEBUG=false
APP_URL=https://edts-image-check.invalid
APP_AUTO_MIGRATE=false
APP_AUTO_SEED=false
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=edts_image_check
DB_USERNAME=edts_check
DB_PASSWORD=synthetic-image-check-only
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=null
REVERB_APP_ID=synthetic
REVERB_APP_KEY=synthetic-public
REVERB_APP_SECRET=synthetic-image-check-only
REVERB_SERVER_PORT=6001
REVERB_ALLOWED_ORIGINS=edts-image-check.invalid
LOG_CHANNEL=stderr
ENV
docker network create "$network" >/dev/null
docker run -d --name "$mysql" --network "$network" --network-alias mysql \
    -e MYSQL_ROOT_PASSWORD=synthetic-root-check-only -e MYSQL_DATABASE=edts_image_check \
    -e MYSQL_USER=edts_check -e MYSQL_PASSWORD=synthetic-image-check-only \
    mysql:8.4.11@sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb >/dev/null
ready=false
for attempt in $(seq 1 60); do
    if docker exec "$mysql" mysqladmin ping -h127.0.0.1 --silent >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
[ "$ready" = true ] || { echo 'Disposable MySQL did not become ready' >&2; exit 1; }
docker run --rm --network "$network" --env-file "$environment" --entrypoint php "$image" artisan migrate --force
docker run --rm --network "$network" --env-file "$environment" --entrypoint php "$image" artisan db:seed --class=StagingReferenceSeeder --force
docker run --rm --network "$network" --env-file "$environment" --entrypoint php "$image" artisan db:seed --class=StagingReferenceSeeder --force
docker run --rm --network "$network" --env-file "$environment" --entrypoint php "$image" deploy/mis-staging/verify-bootstrap.php
docker run --rm --entrypoint nginx "$image" -t
# Force an upstream failure and prove callback query markers never reach logs.
docker run -d --name "$redaction" --entrypoint nginx "$image" -g 'daemon off;' >/dev/null
docker exec "$redaction" php -r '
$url = "http://127.0.0.1:8080/auth/sso/callback?code=SYNTHETIC_CODE_REDACTION&state=SYNTHETIC_STATE_REDACTION";
$context = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 5]]);
for ($attempt = 0; $attempt < 20; $attempt++) {
    @file_get_contents($url, false, $context);
    if (isset($http_response_header[0])) break;
    usleep(100000);
}
if (!isset($http_response_header[0]) || !str_contains($http_response_header[0], " 502 ")) exit(1);
'
# Positive control: an unprotected path must reach the actual Alpine log file.
docker exec "$redaction" php -r '
@file_get_contents("http://127.0.0.1:8080/login?code=SYNTHETIC_ERROR_CONTROL", false, stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 5]]));
'
docker exec "$redaction" grep -q 'SYNTHETIC_ERROR_CONTROL' /var/log/nginx/error.log
if docker exec "$redaction" sh -c 'cat /var/log/nginx/*.log' | grep -qE 'SYNTHETIC_CODE_REDACTION|SYNTHETIC_STATE_REDACTION'; then
    echo 'Callback query leaked into Nginx file logs' >&2
    exit 1
fi
if docker logs "$redaction" 2>&1 | grep -qE 'SYNTHETIC_CODE_REDACTION|SYNTHETIC_STATE_REDACTION'; then
    echo 'Callback query leaked into Nginx logs' >&2
    exit 1
fi
docker rm -fv "$redaction" >/dev/null
docker run -d --name "$app" --network "$network" --env-file "$environment" "$image" >/dev/null
ready=false
for attempt in $(seq 1 60); do
    if docker exec "$app" wget --spider --quiet http://127.0.0.1:8080/up >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
[ "$ready" = true ] || { echo 'Disposable application did not become ready' >&2; exit 1; }
docker exec "$app" wget --spider --quiet http://127.0.0.1:8080/login
docker exec "$app" php -r '$socket = @fsockopen("127.0.0.1", 6001, $code, $message, 5); if (!$socket) exit(1); fclose($socket);'
docker exec "$app" php deploy/mis-staging/verify-bootstrap.php
printf '%s\n' 'Image startup, Nginx, login, Reverb, callback log redaction and fresh MySQL bootstrap passed.'
