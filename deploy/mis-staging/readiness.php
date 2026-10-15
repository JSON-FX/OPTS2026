<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (! $app->environment('staging') || config('app.debug')
        || config('app.url') !== 'https://staging.opts.lguquezon.local'
        || config('services.lgu_sso.api_url') !== 'https://staging.api.sso.lguquezon.local'
        || config('services.lgu_sso.ui_url') !== 'https://staging.sso.lguquezon.local'
        || ! app(App\Services\ConnectPilotHost::class)->configured()
        || config('connect_pilot.connect_origin') !== 'https://staging.connect.lguquezon.local'
        || config('session.cookie') !== 'edts_staging_session'
        || config('mail.default') !== 'log'
        || ! is_string(config('broadcasting.connections.reverb.key'))
        || config('broadcasting.connections.reverb.key') === '') {
        throw new RuntimeException('Staging runtime configuration is incomplete.');
    }
    Illuminate\Support\Facades\DB::select('select 1');
    if (Spatie\Permission\Models\Role::whereIn('name', ['Viewer', 'Endorser', 'Administrator'])->where('guard_name', 'web')->count() !== 3) {
        throw new RuntimeException('Required roles are missing.');
    }
    // Directory transport and client credentials only; no identity or records are changed.
    app(App\Services\SSOService::class)->offices();
    $response = Illuminate\Support\Facades\Http::withoutRedirecting()->connectTimeout(3)->timeout(10)
        ->get(config('connect_pilot.connect_origin').'/up');
    if (! $response->ok()) {
        throw new RuntimeException('Connect health is unavailable.');
    }
    echo "EDTS staging configuration, database, SSO and Connect transport ready.\n";
} catch (Throwable $exception) {
    // Never print exception messages from HTTP clients: they can include request material.
    fwrite(STDERR, 'EDTS staging readiness failed: '.get_class($exception)."\n");
    exit(1);
}
