<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SSOService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SSOServiceTest extends TestCase
{
    private SSOService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SSOService::class);
    }

    public function test_get_login_url_builds_correct_url(): void
    {
        $url = $this->service->getLoginUrl('test-state');

        $this->assertStringContainsString(config('services.lgu_sso.ui_url'), $url);
        $this->assertStringContainsString('state=test-state', $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
    }

    public function test_validate_token_returns_data_on_success(): void
    {
        Http::fake([
            '*/api/v1/sso/validate' => Http::response([
                'employee' => [
                    'uuid' => 'test-uuid',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]),
        ]);

        $result = $this->service->validateToken('valid-token');

        $this->assertNotNull($result);
        $this->assertEquals('test-uuid', $result['employee']['uuid']);
    }

    public function test_validate_token_returns_null_on_failure(): void
    {
        Http::fake([
            '*/api/v1/sso/validate' => Http::response(['error' => 'invalid'], 401),
        ]);

        $result = $this->service->validateToken('invalid-token');

        $this->assertNull($result);
    }

    public function test_authorize_returns_data_on_success(): void
    {
        Http::fake([
            '*/api/v1/sso/authorize' => Http::response([
                'authorized' => true,
                'role' => 'standard',
            ]),
        ]);

        $result = $this->service->authorize('valid-token');

        $this->assertNotNull($result);
        $this->assertTrue($result['authorized']);
    }

    public function test_authorize_returns_null_on_failure(): void
    {
        Http::fake([
            '*/api/v1/sso/authorize' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $result = $this->service->authorize('invalid-token');

        $this->assertNull($result);
    }

    public function test_logout_returns_true_on_success(): void
    {
        Http::fake([
            '*/api/v1/auth/logout' => Http::response(['message' => 'ok']),
        ]);

        $result = $this->service->logout('valid-token');

        $this->assertTrue($result);
    }

    public function test_logout_returns_false_on_failure(): void
    {
        Http::fake([
            '*/api/v1/auth/logout' => Http::response(['error' => 'fail'], 500),
        ]);

        $result = $this->service->logout('invalid-token');

        $this->assertFalse($result);
    }

    public function test_map_sso_role_guest_to_viewer(): void
    {
        $this->assertEquals('Viewer', $this->service->mapSsoRoleToOptsRole('guest'));
    }

    public function test_map_sso_role_standard_to_viewer(): void
    {
        $this->assertEquals('Viewer', $this->service->mapSsoRoleToOptsRole('standard'));
    }

    public function test_map_sso_role_administrator_to_administrator(): void
    {
        $this->assertEquals('Administrator', $this->service->mapSsoRoleToOptsRole('administrator'));
    }

    public function test_map_sso_role_super_administrator_to_administrator(): void
    {
        $this->assertEquals('Administrator', $this->service->mapSsoRoleToOptsRole('super_administrator'));
    }
}
