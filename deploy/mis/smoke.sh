#!/bin/sh
set -eu
case "${1:-}" in
  staging) prefix=staging. ;;
  production) prefix= ;;
  *) echo 'Expected staging or production' >&2; exit 2 ;;
esac
environment=$1
root=/srv/mis-deployment/opts/$environment
ca=/home/mis-ubuntu-1/lguquezon/infra/certs/lguquezonCA.crt
host=${prefix}opts.lguquezon.local
docker compose --project-name "mis-$environment-opts" --env-file "$root/image.env" \
  -f "$root/compose.yml" exec -T --user www-data web \
  php artisan opts:deployment-check --environment="$environment"
curl --fail --silent --show-error --max-time 20 --cacert "$ca" "https://$host/up" >/dev/null
status=$(curl --silent --show-error --max-time 20 --cacert "$ca" \
  -H 'Accept: application/json' -o /dev/null -w '%{http_code}' "https://$host/dashboard")
test "$status" = 401
echo "OPTS $environment HTTPS, protected access and SSO directory checks passed"
