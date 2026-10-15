<?php

namespace Tests\Feature;

use App\Models\ActionTaken;
use App\Models\FundType;
use App\Models\Particular;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\StagingReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MisStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reverb_settings_follow_runtime_without_exposing_secrets(): void
    {
        $this->withoutVite();
        config([
            'app.url' => 'https://staging.opts.lguquezon.local',
            'broadcasting.connections.reverb.key' => 'public-stage-key',
            'broadcasting.connections.reverb.secret' => 'private-reverb-secret',
            'services.lgu_sso.client_secret' => 'private-sso-secret',
        ]);
        $this->get('/login')->assertOk()->assertSee('staging.opts.lguquezon.local')
            ->assertSee('public-stage-key')->assertDontSee('private-reverb-secret')
            ->assertDontSee('private-sso-secret');
        config(['app.url' => 'https://opts.example.invalid:8443', 'broadcasting.connections.reverb.key' => 'public-promoted-key']);
        $this->get('/login')->assertOk()->assertSee('opts.example.invalid')
            ->assertSee('8443')->assertSee('public-promoted-key')->assertDontSee('public-stage-key');
    }

    public function test_staging_bootstrap_is_idempotent_and_creates_no_demo_records(): void
    {
        $this->app['env'] = 'staging';
        $this->seed(StagingReferenceSeeder::class);
        FundType::where('abbreviation', 'GF')->update(['name' => 'Operator label']);
        $this->seed(StagingReferenceSeeder::class);
        $this->assertSame(3, Role::count());
        $this->assertSame(3, FundType::count());
        $this->assertSame(19, ActionTaken::count());
        $this->assertSame('Operator label', FundType::where('abbreviation', 'GF')->value('name'));
        $this->assertSame(0, User::count());
        $this->assertSame(0, Supplier::count());
        $this->assertSame(0, Particular::count());
    }

    public function test_staging_bootstrap_refuses_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        (new StagingReferenceSeeder)->run();
    }

    public function test_reverb_allowed_origins_use_explicit_runtime_hostname(): void
    {
        $environment = \Illuminate\Support\Env::getRepository();
        $previous = $environment->get('REVERB_ALLOWED_ORIGINS');
        try {
            $environment->set('REVERB_ALLOWED_ORIGINS', 'staging.opts.lguquezon.local');
            $settings = require config_path('reverb.php');
            $this->assertSame(['staging.opts.lguquezon.local'], $settings['apps']['apps'][0]['allowed_origins']);
        } finally {
            $previous === null ? $environment->clear('REVERB_ALLOWED_ORIGINS') : $environment->set('REVERB_ALLOWED_ORIGINS', $previous);
        }
    }
}
