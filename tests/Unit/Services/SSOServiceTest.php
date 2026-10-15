<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\SSOUnavailableException;
use App\Services\SSOService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SSOServiceTest extends TestCase
{
    private SSOService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.lgu_sso', [
            'api_url' => 'https://sso.example.test',
            'ui_url' => 'https://portal.example.test',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'https://opts.example.test/auth/sso/callback',
        ]);
        Http::preventStrayRequests();
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

    public function test_authorization_reuse_is_bound_to_the_request_token_and_client(): void
    {
        Http::fake(['*/api/v1/sso/authorize' => Http::response(['authorized' => true, 'role' => 'standard'])]);
        $request = \Illuminate\Http\Request::create('/connect/pilot/launch', 'POST');

        $this->service->authorizeForRequest($request, 'first-token');
        app(SSOService::class)->authorizeForRequest($request, 'first-token');
        Http::assertSentCount(1);

        $this->service->authorizeForRequest($request, 'replacement-token');
        $this->service->authorizeForRequest(\Illuminate\Http\Request::create('/next'), 'first-token');
        config(['services.lgu_sso.client_secret' => 'replacement-client-secret']);
        app(SSOService::class)->authorizeForRequest($request, 'first-token');
        Http::assertSentCount(4);

        // Callers without an inbound request always verify authorization anew.
        $this->service->authorize('first-token');
        $this->service->authorize('first-token');
        Http::assertSentCount(6);
    }

    public function test_denials_are_not_reused_as_successful_request_authorization(): void
    {
        Http::fake(['*/api/v1/sso/authorize' => Http::sequence()
            ->push(['authorized' => false])->push(['authorized' => true, 'role' => 'standard'])]);
        $request = \Illuminate\Http\Request::create('/example');

        $this->assertFalse($this->service->authorizeForRequest($request, 'token')['authorized']);
        $this->assertTrue($this->service->authorizeForRequest($request, 'token')['authorized']);
        Http::assertSentCount(2);
    }

    public static function retryHints(): array
    {
        return [
            'seconds' => ['30', 30],
            'date' => ['Thu, 15 Oct 2026 07:00:45 GMT', 45],
            'past date' => ['Thu, 15 Oct 2026 06:00:00 GMT', 1],
            'zero' => ['0', 1],
            'bounded' => ['9999999999', 300],
            'missing' => [null, 2],
            'invalid' => ['not a delay', 2],
            'negative' => ['-1', 2],
        ];
    }

    #[DataProvider('retryHints')]
    public function test_failure_cooldown_honors_retry_hints_and_recovers_without_caching_grants(?string $hint, int $delay): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 07:00:00 UTC'));
        Http::fake(['*/api/v1/sso/authorize' => Http::sequence()
            ->push([], 429, $hint === null ? [] : ['Retry-After' => $hint])
            ->push(['authorized' => true, 'role' => 'standard'])
            ->push(['authorized' => false])]);

        try {
            $this->service->authorize('token');
            $this->fail('Rate-limited authorization must fail closed.');
        } catch (SSOUnavailableException $exception) {
            $this->assertSame($delay, $exception->retryAfter);
            $this->assertSame((string) $delay, $exception->retryHeaders()['Retry-After']);
        }
        try {
            app(SSOService::class)->offices();
            $this->fail('Another service instance must respect the shared cooldown.');
        } catch (SSOUnavailableException $exception) {
            $this->assertSame($delay, $exception->retryAfter);
        }
        Http::assertSentCount(1);

        $this->travel($delay - 1)->seconds();
        try {
            $this->service->authorize('token');
            $this->fail('The cooldown must last until its deadline.');
        } catch (SSOUnavailableException $exception) {
            $this->assertSame(1, $exception->retryAfter);
        }
        Http::assertSentCount(1);
        $this->travel(1)->seconds();
        $this->assertTrue($this->service->authorize('token')['authorized']);
        $this->assertFalse($this->service->authorize('token')['authorized']);
        Http::assertSentCount(3);
    }

    public function test_upstream_cooldown_is_scoped_to_the_application_client(): void
    {
        Http::fake(['*/api/v1/sso/authorize' => Http::sequence()
            ->push([], 503, ['Retry-After' => '20'])
            ->push(['authorized' => true, 'role' => 'standard'])]);
        try {
            $this->service->authorize('token');
            $this->fail('The first client must fail closed.');
        } catch (SSOUnavailableException $exception) {
            $this->assertSame(20, $exception->retryAfter);
        }
        config(['services.lgu_sso.client_id' => 'different-application']);
        $this->assertTrue(app(SSOService::class)->authorize('token')['authorized']);
        Http::assertSentCount(2);
    }

    public function test_denied_employee_does_not_start_a_client_wide_cooldown(): void
    {
        Http::fake(['*/api/v1/sso/authorize' => Http::sequence()
            ->push([], 401)->push([], 403)->push(['authorized' => true, 'role' => 'standard'])]);
        $this->assertNull($this->service->authorize('expired-token'));
        $this->assertNull($this->service->authorize('removed-grant-token'));
        $this->assertTrue($this->service->authorize('other-employee-token')['authorized']);
        Http::assertSentCount(3);
    }

    public function test_connection_failure_is_not_automatically_retried(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('synthetic outage');
        });
        foreach (['authorize', 'offices'] as $method) {
            try {
                $method === 'authorize' ? $this->service->authorize('token') : $this->service->offices();
                $this->fail('The request must fail closed.');
            } catch (SSOUnavailableException $exception) {
                $this->assertSame(2, $exception->retryAfter);
            }
        }
        $this->assertSame(1, $attempts);
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

    public function test_map_sso_role_standard_to_endorser(): void
    {
        $this->assertEquals('Endorser', $this->service->mapSsoRoleToOptsRole('standard'));
    }

    public function test_map_sso_role_administrator_to_administrator(): void
    {
        $this->assertEquals('Administrator', $this->service->mapSsoRoleToOptsRole('administrator'));
    }

    public function test_map_sso_role_super_administrator_to_administrator(): void
    {
        $this->assertEquals('Administrator', $this->service->mapSsoRoleToOptsRole('super_administrator'));
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->mapSsoRoleToOptsRole('owner');
    }

    #[DataProvider('unavailableResponses')]
    public function test_authorization_errors_fail_closed(int $status, mixed $payload): void
    {
        Http::fake(['*/api/v1/sso/authorize' => Http::response($payload, $status)]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->authorize('valid-token');
    }

    public static function unavailableResponses(): array
    {
        return [
            'server error' => [500, ['message' => 'Unavailable']],
            'rate limited' => [429, ['message' => 'Try later']],
            'malformed JSON' => [200, 'not-json'],
            'missing decision' => [200, ['role' => 'standard']],
            'nonboolean decision' => [200, ['authorized' => 'true']],
        ];
    }

    public function test_authorization_network_failure_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Network unavailable'));

        $this->expectException(SSOUnavailableException::class);
        $this->service->authorize('valid-token');
    }

    public function test_directory_uses_server_roles_and_credentials_and_only_allowed_query_fields(): void
    {
        Http::fake(['*/api/v1/sso/directory*' => Http::response($this->peoplePage())]);

        $result = $this->service->directory([
            'search' => 'Demo User',
            'page' => 2,
            'per_page' => 10,
            'roles' => ['owner'],
            'application_id' => 999,
            'client_id' => 'attacker',
            'client_secret' => 'attacker-secret',
            'include' => 'private_profile',
        ]);

        $this->assertSame($this->person(), $result['data'][0]);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $request->hasHeader('X-Client-ID', 'test-client')
                && $request->hasHeader('X-Client-Secret', 'test-secret')
                && $query === [
                    'search' => 'Demo User',
                    'page' => '2',
                    'per_page' => '10',
                    'roles' => SSOService::DIRECTORY_ROLES,
                ]
                && ! str_contains($request->url(), 'test-secret')
                && ! str_contains($request->url(), 'test-client');
        });
    }

    public function test_directory_removes_undocumented_private_fields(): void
    {
        $page = $this->peoplePage();
        $page['data'][0] += ['email' => 'private@example.test', 'username' => 'private', 'grants' => ['other-app']];
        $page['total_employee_count'] = 500;
        Http::fake(['*/api/v1/sso/directory*' => Http::response($page)]);

        $this->assertSame($this->peoplePage(), $this->service->directory());
    }

    public function test_directory_preserves_pagination_and_accepts_empty_last_page(): void
    {
        $first = $this->peoplePage();
        $first['meta']['has_more'] = true;
        $last = ['data' => [], 'meta' => ['page' => 2, 'per_page' => 20, 'has_more' => false]];
        Http::fake(['*/api/v1/sso/directory*' => Http::sequence()->push($first)->push($last)]);

        $this->assertSame($first, $this->service->directory(['page' => 1]));
        $this->assertSame($last, $this->service->directory(['page' => 2]));
    }

    public function test_directory_user_returns_only_current_display_fields(): void
    {
        $person = $this->person();
        Http::fake(['*/api/v1/sso/directory/*' => Http::response(['data' => $person + ['email' => 'private@example.test']])]);

        $this->assertSame($person, $this->service->directoryUser($person['uuid']));
        Http::assertSent(function (Request $request) use ($person): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_PATH) === '/api/v1/sso/directory/'.$person['uuid']
                && $query === ['roles' => SSOService::DIRECTORY_ROLES]
                && $request->hasHeader('X-Client-ID', 'test-client')
                && $request->hasHeader('X-Client-Secret', 'test-secret');
        });
    }

    public function test_directory_user_missing_or_ineligible_returns_null(): void
    {
        Http::fake(['*/api/v1/sso/directory/*' => Http::response(['message' => 'Employee not found.'], 404)]);

        $this->assertNull($this->service->directoryUser($this->person()['uuid']));
    }

    public function test_directory_user_invalid_uuid_does_not_make_request(): void
    {
        Http::fake();

        $this->assertNull($this->service->directoryUser('../offices'));
        Http::assertNothingSent();
    }

    public function test_directory_user_rejects_mismatched_identity(): void
    {
        Http::fake(['*/api/v1/sso/directory/*' => Http::response(['data' => $this->person()])]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->directoryUser('00000000-0000-4000-8000-000000000002');
    }

    #[DataProvider('directoryErrorStatuses')]
    public function test_directory_errors_never_return_empty_success(int $status): void
    {
        Http::fake(['*/api/v1/sso/directory*' => Http::response(['message' => 'Unavailable'], $status)]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->directory();
    }

    public static function directoryErrorStatuses(): array
    {
        return [[401], [403], [404], [422], [429], [500], [503]];
    }

    public function test_directory_network_failure_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Network unavailable'));

        $this->expectException(SSOUnavailableException::class);
        $this->service->directory();
    }

    #[DataProvider('invalidDirectoryPages')]
    public function test_malformed_directory_pages_are_rejected(array $page): void
    {
        Http::fake(['*/api/v1/sso/directory*' => Http::response($page)]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->directory();
    }

    public static function invalidDirectoryPages(): array
    {
        $meta = ['page' => 1, 'per_page' => 20, 'has_more' => false];

        return [
            'missing data' => [['meta' => $meta]],
            'missing metadata' => [['data' => []]],
            'invalid pagination' => [['data' => [], 'meta' => array_replace($meta, ['page' => 0])]],
            'nonboolean continuation' => [['data' => [], 'meta' => array_replace($meta, ['has_more' => '0'])]],
            'object instead of list' => [['data' => ['person' => []], 'meta' => $meta]],
            'missing required identity' => [['data' => [['full_name' => 'Demo']], 'meta' => $meta]],
        ];
    }

    public function test_unknown_directory_role_is_rejected(): void
    {
        $page = $this->peoplePage();
        $page['data'][0]['role'] = 'owner';
        Http::fake(['*/api/v1/sso/directory*' => Http::response($page)]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->directory();
    }

    public function test_offices_request_page_and_preserve_reference_fields_only(): void
    {
        $office = ['id' => 27, 'name' => 'Municipal Budget Office', 'abbreviation' => 'MBO', 'is_active' => true];
        $page = ['data' => [$office], 'meta' => ['page' => 2, 'per_page' => 100, 'has_more' => true]];
        $payload = $page;
        $payload['data'][0]['employee_count'] = 42;
        Http::fake(['*/api/v1/sso/offices*' => Http::response($payload)]);

        $this->assertSame($page, $this->service->offices(2));
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === ['page' => '2', 'per_page' => '100']
                && $request->hasHeader('X-Client-ID', 'test-client')
                && $request->hasHeader('X-Client-Secret', 'test-secret');
        });
    }

    public function test_offices_reject_unexpected_page(): void
    {
        Http::fake(['*/api/v1/sso/offices*' => Http::response([
            'data' => [], 'meta' => ['page' => 1, 'per_page' => 100, 'has_more' => false],
        ])]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->offices(2);
    }

    public function test_office_detail_can_resolve_inactive_historical_office(): void
    {
        $office = ['id' => 27, 'name' => 'Municipal Budget Office', 'abbreviation' => 'MBO', 'is_active' => false];
        Http::fake(['*/api/v1/sso/offices/27' => Http::response(['data' => $office])]);

        $this->assertSame($office, $this->service->office(27));
    }

    public function test_office_detail_missing_returns_null(): void
    {
        Http::fake(['*/api/v1/sso/offices/27' => Http::response([], 404)]);

        $this->assertNull($this->service->office(27));
    }

    public function test_office_detail_rejects_mismatched_identity(): void
    {
        Http::fake(['*/api/v1/sso/offices/27' => Http::response(['data' => [
            'id' => 28, 'name' => 'Municipal Budget Office', 'abbreviation' => 'MBO', 'is_active' => true,
        ]])]);

        $this->expectException(SSOUnavailableException::class);
        $this->service->office(27);
    }

    public function test_rate_limit_logging_contains_only_sanitized_endpoint_status_and_retry_after(): void
    {
        Log::spy();
        Http::fake(['*/api/v1/sso/directory/*' => Http::response(['message' => 'Private upstream body'], 429, ['Retry-After' => '17'])]);
        try {
            $this->service->directoryUser('00000000-0000-4000-8000-000000000001');
            $this->fail('Expected upstream rate limit');
        } catch (SSOUnavailableException) {
            Log::shouldHaveReceived('warning')->once()->with('SSO upstream request failed.', [
                'endpoint' => '/sso/directory/{id}', 'status' => 429, 'retry_after' => '17',
            ]);
        }
    }

    private function peoplePage(): array
    {
        return ['data' => [$this->person()], 'meta' => ['page' => 1, 'per_page' => 20, 'has_more' => false]];
    }

    private function person(): array
    {
        return [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'full_name' => 'Demo User',
            'initials' => 'D.U',
            'role' => 'standard',
            'office_id' => 27,
            'office_name' => 'Municipal Budget Office',
            'office_abbreviation' => 'MBO',
            'position_id' => null,
            'position_title' => null,
        ];
    }
}
