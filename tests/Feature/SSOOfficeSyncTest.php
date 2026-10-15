<?php

namespace Tests\Feature;

use App\Exceptions\SSOUnavailableException;
use App\Models\Office;
use App\Models\Procurement;
use App\Services\SSOOfficeService;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SSOOfficeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function record(int $id, string $abbreviation, string $name = 'Office', bool $active = true): array
    {
        return ['id' => $id, 'name' => $name, 'abbreviation' => $abbreviation, 'is_active' => $active];
    }

    private function page(array $records, bool $more = false): array
    {
        return ['data' => $records, 'meta' => ['has_more' => $more]];
    }

    public function test_all_pages_sync_and_exact_mapping_preserves_business_foreign_keys(): void
    {
        $local = Office::factory()->create(['sso_id' => null, 'abbreviation' => 'MBO', 'name' => 'Old budget name']);
        $procurement = Procurement::factory()->create(['end_user_id' => $local->id]);
        $legacy = Office::factory()->create(['sso_id' => null]);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('offices')->with(1)->once()->andReturn($this->page([$this->record(27, 'MBO', 'Municipal Budget Office')], true));
        $api->shouldReceive('offices')->with(2)->once()->andReturn($this->page([$this->record(64, 'MTO', 'Municipal Treasurer Office')]));
        $report = app(SSOOfficeService::class)->sync();
        $this->assertSame(2, $report['active']);
        $this->assertSame($local->id, $procurement->fresh()->end_user_id);
        $this->assertSame(27, $local->fresh()->sso_id);
        $this->assertSame('Municipal Budget Office', $local->fresh()->name);
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertDatabaseHas('offices', ['sso_id' => 64, 'abbreviation' => 'MTO', 'is_active' => true]);
    }

    public function test_rename_and_inactive_detail_preserve_identity(): void
    {
        $local = Office::factory()->create(['sso_id' => 27]);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('offices')->with(1)->once()->andReturn($this->page([]));
        $api->shouldReceive('office')->with(27)->once()->andReturn($this->record(27, 'BUDGET', 'Renamed Budget Office', false));
        app(SSOOfficeService::class)->sync();
        $this->assertSame('BUDGET', $local->fresh()->abbreviation);
        $this->assertFalse($local->fresh()->is_active);
        $this->assertSame(27, $local->fresh()->sso_id);
    }

    public function test_later_page_failure_does_not_change_existing_snapshot(): void
    {
        $local = Office::factory()->create(['sso_id' => null, 'abbreviation' => 'MBO']);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('offices')->with(1)->andReturn($this->page([$this->record(27, 'MBO')], true));
        $api->shouldReceive('offices')->with(2)->andThrow(new SSOUnavailableException('Unavailable'));
        try {
            app(SSOOfficeService::class)->sync();
            $this->fail('Expected upstream failure');
        } catch (SSOUnavailableException) {
            $this->assertTrue($local->fresh()->is_active);
            $this->assertNull($local->fresh()->sso_id);
        }
    }

    public function test_ambiguous_mapping_rolls_back_all_changes(): void
    {
        $one = Office::factory()->create(['sso_id' => null, 'abbreviation' => 'MBO', 'name' => 'First office']);
        $two = Office::factory()->create(['sso_id' => null, 'abbreviation' => 'OTHER', 'name' => 'Budget']);
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('offices')->andReturn($this->page([$this->record(27, 'MBO', 'Budget')]));
        try {
            app(SSOOfficeService::class)->sync();
            $this->fail('Expected ambiguous mapping rejection');
        } catch (SSOUnavailableException) {
            $this->assertTrue($one->fresh()->is_active);
            $this->assertTrue($two->fresh()->is_active);
            $this->assertSame(0, Office::whereNotNull('sso_id')->count());
        }
    }

    public function test_metadata_refresh_expires_and_resolves_current_inactive_assignment(): void
    {
        Cache::flush();
        $api = $this->mock(SSOService::class);
        $api->shouldReceive('offices')->twice()->andReturn($this->page([$this->record(27, 'MBO')]));
        $service = app(SSOOfficeService::class);
        $service->refreshIfStale();
        $service->refreshIfStale();
        $this->travel(61)->seconds();
        $service->refreshIfStale();
        $api->shouldReceive('office')->with(27)->once()->andReturn($this->record(27, 'MBO', 'Office', false));
        $this->assertFalse($service->resolve(27)->is_active);
    }
}
