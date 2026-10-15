<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SSOService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentCheck extends Command
{
    protected $signature = 'opts:deployment-check {--environment=staging}';

    protected $description = 'Check MIS configuration, database and SSO directory readiness without changing records';

    public function handle(SSOService $sso): int
    {
        $environment = $this->option('environment');
        if (! in_array($environment, ['staging', 'production'], true)) {
            $this->error('Expected staging or production.');

            return self::FAILURE;
        }

        $prefix = $environment === 'staging' ? 'staging.' : '';
        $app = 'https://'.$prefix.'opts.lguquezon.local';
        $api = 'https://'.($environment === 'staging' ? 'staging.' : '').'api.sso.lguquezon.local';
        $portal = 'https://'.$prefix.'sso.lguquezon.local';
        $client = config('services.lgu_sso.client_id');
        $secret = config('services.lgu_sso.client_secret');
        $checks = [
            config('app.env') === $environment,
            config('app.url') === $app,
            config('app.debug') === false,
            config('broadcasting.connections.reverb.public.host') === parse_url($app, PHP_URL_HOST),
            config('broadcasting.connections.reverb.public.port') === 443,
            config('broadcasting.connections.reverb.public.scheme') === 'https',
            config('services.lgu_sso.api_url') === $api,
            config('services.lgu_sso.ui_url') === $portal,
            config('services.lgu_sso.redirect_uri') === $app.'/auth/sso/callback',
            is_string($client) && $client !== '' && ! str_contains($client, 'your-') && ! str_contains($client, '<'),
            is_string($secret) && strlen($secret) >= 32 && ! str_contains($secret, '<'),
            config('session.driver') === 'database',
            config('session.encrypt') === true,
            config('session.secure') === true,
            config('session.http_only') === true,
            config('session.same_site') === 'lax',
            config('session.domain') === null,
            config('mail.default') === 'log',
        ];
        if (in_array(false, $checks, true)) {
            $this->error('MIS URL, SSO client, outbound mail or session configuration is not ready.');

            return self::FAILURE;
        }

        try {
            DB::select('select 1');
            $sso->offices();
        } catch (Throwable) {
            // Upstream exceptions may contain credentials or directory details.
            $this->error('Database or authenticated SSO office-directory check failed.');

            return self::FAILURE;
        }

        $this->info('MIS configuration, database and SSO directory checks passed. Employee login still requires browser acceptance.');

        return self::SUCCESS;
    }
}
