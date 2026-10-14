<?php

namespace Tests\Feature;

use App\Exceptions\SSOUnavailableException;
use App\Models\User;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Viewer', 'Endorser', 'Administrator'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_directory_uses_live_sso_people_and_only_safe_display_fields(): void
    {
        User::factory()->create(['name' => 'Local historical person']);
        $this->partialMock(SSOService::class, function ($mock) {
            $mock->shouldReceive('directory')->once()->with(['search' => '', 'page' => 1, 'per_page' => 20])
                ->andReturn([
                    'data' => array_map(fn ($role) => [
                        ...$this->person(), 'role' => $role,
                        'email' => 'private@example.test', 'username' => 'private', 'grants' => ['other-app'],
                    ], ['guest', 'standard', 'administrator', 'super_administrator']),
                    'meta' => ['page' => 1, 'per_page' => 20, 'has_more' => false],
                ]);
        });

        $this->actingAs($this->user('Administrator'))->get('/admin/users')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('directory.data', 4)
                ->where('directory.data.0.full_name', 'Demo Mayor')
                ->where('directory.data.0.opts_role', 'Viewer')
                ->where('directory.data.1.opts_role', 'Endorser')
                ->where('directory.data.2.opts_role', 'Administrator')
                ->where('directory.data.3.opts_role', 'Administrator')
                ->missing('directory.data.0.email')
                ->missing('directory.data.0.username')
                ->missing('directory.data.0.grants')
                ->missing('users')
                ->missing('roles')
                ->missing('offices'));
    }

    public function test_search_and_pagination_forward_only_allowed_query_fields(): void
    {
        $this->partialMock(SSOService::class, function ($mock) {
            $mock->shouldReceive('directory')->once()->with(['search' => 'Demo Mayor', 'page' => 2, 'per_page' => 20])
                ->andReturn(['data' => [], 'meta' => ['page' => 2, 'per_page' => 20, 'has_more' => true]]);
        });

        $this->actingAs($this->user('Administrator'))
            ->get('/admin/users?search=Demo%20Mayor&page=2&roles[]=super_administrator&per_page=50&application_id=other')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'Demo Mayor')
                ->where('directory.meta.page', 2)
                ->where('directory.meta.has_more', true));
    }

    public function test_invalid_directory_queries_are_rejected_before_sso(): void
    {
        $this->mock(SSOService::class)->shouldNotReceive('directory');

        $this->actingAs($this->user('Administrator'))->getJson('/admin/users?page=0&search='.str_repeat('a', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'search']);
    }

    public function test_directory_failure_returns_503_without_local_fallback(): void
    {
        User::factory()->create(['name' => 'Private local record']);
        $this->mock(SSOService::class)->shouldReceive('directory')->once()
            ->andThrow(new SSOUnavailableException('Sensitive upstream failure'));

        $this->actingAs($this->user('Administrator'))->get('/admin/users')
            ->assertStatus(503)
            ->assertDontSee('Private local record')
            ->assertDontSee('Sensitive upstream failure');
    }

    public function test_viewers_and_endorsers_cannot_access_directory(): void
    {
        $this->mock(SSOService::class)->shouldNotReceive('directory');

        foreach (['Viewer', 'Endorser'] as $role) {
            $this->actingAs($this->user($role))->get('/admin/users')->assertForbidden();
        }
    }

    public function test_sso_owned_users_cannot_be_created_edited_or_deleted_locally(): void
    {
        $admin = $this->user('Administrator');
        $user = $this->user('Viewer');
        $this->actingAs($admin);

        $this->get('/admin/users/create')->assertNotFound();
        $this->post('/admin/users', ['name' => 'Unexpected user'])->assertStatus(405);
        $this->get("/admin/users/{$user->id}/edit")->assertNotFound();
        $this->put("/admin/users/{$user->id}", ['role' => 'Administrator', 'is_active' => false])->assertNotFound();
        $this->delete("/admin/users/{$user->id}")->assertNotFound();
        $this->assertTrue($user->fresh()->hasRole('Viewer'));
        $this->assertTrue($user->fresh()->is_active);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function person(): array
    {
        return [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'full_name' => 'Demo Mayor', 'initials' => 'D.M', 'role' => 'guest',
            'office_id' => 34, 'office_name' => 'Municipal Mayor Office', 'office_abbreviation' => 'MMO',
            'position_id' => 1, 'position_title' => 'Municipal Mayor',
        ];
    }
}
