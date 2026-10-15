#!/bin/sh
set -eu
cd /srv/mis-deployment/edts/staging
docker compose --project-name mis-staging-edts --env-file image.env -f compose.yml exec -T app php deploy/mis-staging/readiness.php
curl --fail --silent --show-error --max-time 20 --cacert office-ca.crt https://staging.opts.lguquezon.local/up >/dev/null
curl --fail --silent --show-error --max-time 20 --cacert office-ca.crt https://staging.opts.lguquezon.local/login >/dev/null
