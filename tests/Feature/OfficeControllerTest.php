<?php

namespace Tests\Feature;

use App\Exceptions\SSOUnavailableException;
use App\Models\Office;
use App\Models\User;
use App\Services\SSOOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Viewer', 'Endorser', 'Administrator'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_directory_refreshes_metadata_and_preserves_historical_mappings(): void
    {
        $mapped = Office::factory()->create(['sso_id' => 34, 'is_active' => true]);
        $historical = Office::factory()->create(['sso_id' => null, 'abbreviation' => 'ZZ-HISTORY', 'is_active' => false]);
        $this->mock(SSOOfficeService::class)->shouldReceive('refreshIfStale')->once()->andReturnUsing(function () use ($mapped) {
            $mapped->update(['name' => 'Updated SSO Office', 'abbreviation' => 'AA', 'sso_synced_at' => now()]);
        });

        $this->actingAs($this->user('Administrator'))->get('/admin/repositories/offices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Repositories/Offices/Index')
                ->has('offices.data', 2)
                ->where('offices.data.0.id', $mapped->id)
                ->where('offices.data.0.sso_id', 34)
                ->where('offices.data.0.name', 'Updated SSO Office')
                ->where('offices.data.0.is_active', true)
                ->where('offices.data.1.id', $historical->id)
                ->where('offices.data.1.sso_id', null)
                ->where('offices.data.1.is_active', false)
                ->missing('offices.data.0.users'));
    }

    public function test_directory_paginates_and_searches_all_offices(): void
    {
        $this->mock(SSOOfficeService::class)->shouldReceive('refreshIfStale')->twice();
        Office::factory()->count(55)->sequence(fn ($sequence) => [
            'name' => 'Pagination Office '.$sequence->index,
            'abbreviation' => 'PAGE-'.$sequence->index,
            'sso_id' => 1000 + $sequence->index,
        ])->create();
        $wanted = Office::factory()->create(['name' => 'Unique Directory Office', 'abbreviation' => 'UDIR', 'sso_id' => 2000]);
        $this->actingAs($this->user('Administrator'));

        $this->get('/admin/repositories/offices')->assertInertia(fn (Assert $page) => $page
            ->has('offices.data', 50)->where('offices.total', 56));
        $this->get('/admin/repositories/offices?search=UDIR')->assertInertia(fn (Assert $page) => $page
            ->has('offices.data', 1)->where('offices.data.0.id', $wanted->id));
    }

    public function test_office_directory_failure_returns_503_without_stale_data(): void
    {
        Office::factory()->create(['name' => 'Stale Directory Office']);
        $this->mock(SSOOfficeService::class)->shouldReceive('refreshIfStale')->once()
            ->andThrow(new SSOUnavailableException('Sensitive upstream failure'));

        $this->actingAs($this->user('Administrator'))->get('/admin/repositories/offices')
            ->assertStatus(503)->assertDontSee('Stale Directory Office')->assertDontSee('Sensitive upstream failure');
    }

    public function test_viewers_and_endorsers_cannot_access_office_directory(): void
    {
        $this->mock(SSOOfficeService::class)->shouldNotReceive('refreshIfStale');

        foreach (['Viewer', 'Endorser'] as $role) {
            $this->actingAs($this->user($role))->get('/admin/repositories/offices')->assertForbidden();
        }
    }

    public function test_sso_owned_offices_cannot_be_created_edited_or_deleted_locally(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create(['office_id' => $office->id]);
        $this->actingAs($this->user('Administrator'));

        $this->get('/admin/repositories/offices/create')->assertNotFound();
        $this->post('/admin/repositories/offices', ['name' => 'Unexpected office'])->assertStatus(405);
        $this->get("/admin/repositories/offices/{$office->id}/edit")->assertNotFound();
        $this->put("/admin/repositories/offices/{$office->id}", ['name' => 'Unexpected change'])->assertNotFound();
        $this->delete("/admin/repositories/offices/{$office->id}")->assertNotFound();
        $this->assertNotNull($office->fresh());
        $this->assertEquals($office->id, $user->fresh()->office_id);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
