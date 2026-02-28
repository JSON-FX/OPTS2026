<?php

declare(strict_types=1);

namespace Tests\Feature\Migration;

use App\Models\MigrationImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Create roles if using Spatie
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Administrator']);
        $viewerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Viewer']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('Viewer');
    }

    public function test_index_page_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.migration.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Migration/Index')
            ->has('imports')
        );
    }

    public function test_index_page_blocked_for_non_admin(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('admin.migration.index'));

        $response->assertStatus(403);
    }

    public function test_index_page_blocked_for_guest(): void
    {
        $response = $this->get(route('admin.migration.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.migration.upload'), []);

        $response->assertSessionHasErrors('sql_file');
    }

    public function test_mappings_page_loads_for_admin(): void
    {
        $import = MigrationImport::create([
            'filename' => 'test.sql',
            'batch_id' => 'test-batch-123',
            'status' => MigrationImport::STATUS_ANALYZING,
            'imported_by_user_id' => $this->admin->id,
            'mapping_data' => [
                'offices' => [],
                'users' => [],
                'particulars' => [],
                'action_taken' => [],
                'source_counts' => ['transactions' => 0, 'endorsements' => 0, 'events' => 0, 'users' => 0, 'offices' => 0],
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.migration.mappings', $import));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Migration/Mappings')
            ->has('import')
            ->has('offices')
        );
    }

    public function test_rollback_updates_status(): void
    {
        $import = MigrationImport::create([
            'filename' => 'test.sql',
            'batch_id' => 'rollback-test-123',
            'status' => MigrationImport::STATUS_COMPLETED,
            'imported_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.migration.rollback', $import));

        $response->assertRedirect(route('admin.migration.index'));
        $this->assertDatabaseHas('migration_imports', [
            'id' => $import->id,
            'status' => MigrationImport::STATUS_ROLLED_BACK,
        ]);
    }

    public function test_execute_requires_dry_run_status(): void
    {
        $import = MigrationImport::create([
            'filename' => 'test.sql',
            'batch_id' => 'execute-test-123',
            'status' => MigrationImport::STATUS_ANALYZING,
            'imported_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.migration.execute', $import));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
