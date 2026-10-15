#!/usr/bin/env python3
"""Verify the operator templates with Compose without reading runtime secrets."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
registration = json.loads((root / 'deploy/mis/opts.json').read_text())
assert registration['repository'] == 'JSON-FX/OPTS2026'
assert registration['branch'] == 'staging'
assert registration['workflow'] == 'mis-image.yml'
assert registration['image_repository'] == 'mis-opts'
rendered = {}
for environment, prefix in [('staging', 'staging.'), ('production', '')]:
    target = registration[environment]
    assert target['services'] == ['web']
    assert target['migrate_argv'] == []  # The existing app entrypoint migrates once.
    assert target['image_env_file'] == f'/srv/mis-deployment/opts/{environment}/image.env'
    source = (root / f'deploy/mis/{environment}.compose.yml').read_text()
    # Some Compose versions still stat env_file paths with --no-env-resolution.
    # Validate their exact production paths, then render a secret-free fixture.
    for name in ['runtime', 'database']:
        entry = f'env_file: /srv/mis-deployment/opts/{environment}/{name}.env'
        assert source.count(entry) == 1
        source = source.replace(entry, 'env_file: []')
    with tempfile.TemporaryDirectory(prefix='opts-compose-check-') as directory:
        fixture = Path(directory) / 'compose.yml'
        fixture.write_text(source)
        command = ['docker', 'compose', '-f', str(fixture), 'config', '--format', 'json']
        config = json.loads(subprocess.check_output(command, env={**os.environ, 'APP_IMAGE': 'mis-opts:check'}))
    web = config['services']['web']
    values = web['environment']
    host = f'{prefix}opts.lguquezon.local'
    assert values['APP_URL'] == f'https://{host}'
    assert values['APP_ENV'] == environment
    assert values['DB_HOST'] == 'mysql'
    assert values['REVERB_PUBLIC_HOST'] == host
    assert values['REVERB_PUBLIC_PORT'] == '443'
    assert values['REVERB_PUBLIC_SCHEME'] == 'https'
    assert values['SSO_API_URL'] == f'https://{prefix}api.sso.lguquezon.local'
    assert values['SSO_UI_URL'] == f'https://{prefix}sso.lguquezon.local'
    assert values['SSO_REDIRECT_URI'] == f'https://{host}/auth/sso/callback'
    assert values['SESSION_DOMAIN'] == 'null'
    for key in ['SESSION_ENCRYPT', 'SESSION_SECURE_COOKIE', 'SESSION_HTTP_ONLY']:
        assert values[key] == 'true'
    assert values['MAIL_MAILER'] == 'log'
    assert not web.get('ports') and not config['services']['mysql'].get('ports')
    assert config['networks']['data']['internal'] is True
    assert 'ingress' not in config['services']['mysql']['networks']
    assert web['healthcheck'] and web['image'] == 'mis-opts:check'
    assert all('docker.sock' not in volume.get('source', '') for volume in web['volumes'])
    rendered[environment] = values
    for volume in ['storage', 'mysql']:
        assert config['volumes'][volume]['name'].startswith(f'mis-{environment}-opts_')
# Runtime settings differ only in environment and hostnames, not behavior.
stage = {key: value.replace('staging.', '') for key, value in rendered['staging'].items() if key != 'APP_ENV'}
production = {key: value for key, value in rendered['production'].items() if key != 'APP_ENV'}
assert stage == production
workflow = (root / '.github/workflows/mis-image.yml').read_text()
assert 'needs: [php, frontend]' in workflow
assert "github.event_name == 'push' && github.ref == 'refs/heads/staging'" in workflow
assert 'org.opencontainers.image.revision="$GITHUB_SHA"' in workflow
assert '--output type=docker,dest=image.tar' in workflow
assert 'image.tar.gz.sha256' in workflow
assert 'ssh ' not in workflow and 'self-hosted' not in workflow
assert 'REVERB_APP_SECRET' not in (root / 'resources/views/app.blade.php').read_text()
assert '--class=RoleSeeder' in (root / 'docker/entrypoint.sh').read_text()

for file in ['docker/nginx.conf', 'deploy/mis/staging.nginx.conf', 'deploy/mis/production.nginx.conf']:
    text = (root / file).read_text()
    assert '$request_method $uri $server_protocol' in text
    assert '$http_referer' not in text and '$request"' not in text
print('MIS staging/production isolation, parity and image delivery contract passed.')

# A prepared adapter must not activate through copied deployment examples.
loader = json.loads((root / 'deploy/connect-widget/loader-manifest.json').read_text())
assert loader['sha256'] == '0a9ddcdf1dcda9a46c66712e91b5443678ddb1c5911d78038bc9b51366f53c81'
assert hashlib.sha256((root / loader['asset']).read_bytes()).hexdigest() == loader['sha256']
for environment, prefix in [('staging', 'staging.'), ('production', '')]:
    lines = (root / f'deploy/connect-widget/{environment}.env.example').read_text().splitlines()
    values = dict(line.split('=', 1) for line in lines if line and not line.startswith('#'))
    assert values['CONNECT_PILOT_ENABLED'] == 'false'
    assert values['CONNECT_PILOT_SECRET'] == ''
    assert values['APP_URL'] == f'https://{prefix}opts.lguquezon.local'
    assert values['CONNECT_PILOT_ORIGIN'] == f'https://{prefix}connect.lguquezon.local'
    assert values['CONNECT_PILOT_HOST_ID'] == f'opts-{environment}'
    assert values['CONNECT_PILOT_KEY_ID'] == f'opts-{environment}-v1'
    assert values['SSO_API_URL'] == f'https://{prefix}api.sso.lguquezon.local'
    assert values['SSO_UI_URL'] == f'https://{prefix}sso.lguquezon.local'
    assert values['SSO_REDIRECT_URI'] == values['APP_URL'] + '/auth/sso/callback'
assert 'CONNECT_PILOT_ENABLED=false' in (root / '.env.example').read_text()
assert 'node --test tests/frontend/connect-widget.test.cjs' in workflow
print('Connect disabled defaults, environment separation and pinned loader passed.')
