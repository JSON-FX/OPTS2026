<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SSOService;
use Tests\TestCase;

class DeploymentCheckTest extends TestCase
{
    private function configure(string $prefix = 'staging.'): void
    {
        config([
            'app.env' => $prefix === 'staging.' ? 'staging' : 'production',
            'app.url' => 'https://'.$prefix.'opts.lguquezon.local',
            'app.debug' => false,
            'broadcasting.connections.reverb.public' => ['host' => $prefix.'opts.lguquezon.local', 'port' => 443, 'scheme' => 'https'],
            'services.lgu_sso.api_url' => 'https://'.$prefix.'api.sso.lguquezon.local',
            'services.lgu_sso.ui_url' => 'https://'.$prefix.'sso.lguquezon.local',
            'services.lgu_sso.redirect_uri' => 'https://'.$prefix.'opts.lguquezon.local/auth/sso/callback',
            'services.lgu_sso.client_id' => 'synthetic-mis-client',
            'services.lgu_sso.client_secret' => str_repeat('x', 32),
            'session.driver' => 'database', 'session.encrypt' => true,
            'session.secure' => true, 'session.http_only' => true,
            'session.same_site' => 'lax', 'session.domain' => null,
            'mail.default' => 'log',
        ]);
    }

    public function test_staging_checks_the_authenticated_directory_without_writing_records(): void
    {
        $this->configure();
        $this->mock(SSOService::class, fn ($mock) => $mock->shouldReceive('offices')->once()->andReturn(['data' => [], 'meta' => []]));
        $this->artisan('opts:deployment-check')->assertSuccessful();
    }

    public function test_production_uses_its_own_hosts(): void
    {
        $this->configure('');
        $this->mock(SSOService::class, fn ($mock) => $mock->shouldReceive('offices')->once()->andReturn([]));
        $this->artisan('opts:deployment-check --environment=production')->assertSuccessful();
    }

    public function test_mixed_environment_or_insecure_sessions_fail_before_upstream_access(): void
    {
        foreach (['services.lgu_sso.api_url' => 'https://api.sso.lguquezon.local', 'session.encrypt' => false, 'session.domain' => '.lguquezon.local'] as $key => $value) {
            $this->configure();
            config([$key => $value]);
            $this->mock(SSOService::class, fn ($mock) => $mock->shouldNotReceive('offices'));
            $this->artisan('opts:deployment-check')->assertFailed();
        }
    }

    public function test_upstream_failure_is_redacted_and_fails_readiness(): void
    {
        $this->configure();
        $this->mock(SSOService::class, fn ($mock) => $mock->shouldReceive('offices')->once()->andThrow(new \RuntimeException('secret-value')));
        $this->artisan('opts:deployment-check')->expectsOutputToContain('check failed')->doesntExpectOutputToContain('secret-value')->assertFailed();
    }
}
