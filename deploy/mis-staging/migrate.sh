#!/bin/sh
set -eu
cd /srv/mis-deployment/edts/staging
# MySQL is provisioned once before the first deployment; never use migrate:fresh.
docker compose --project-name mis-staging-edts --env-file image.env -f compose.yml run --rm --no-deps --entrypoint php app artisan migrate --force
docker compose --project-name mis-staging-edts --env-file image.env -f compose.yml run --rm --no-deps --entrypoint php app artisan db:seed --class=StagingReferenceSeeder --force
