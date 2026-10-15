<?php

namespace Tests\Feature;

use App\Models\FundType;
use App\Models\Office;
use App\Models\Procurement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReferenceNumberService;
use App\Services\WorkflowAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutomaticDocumentReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15));
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $user->assignRole('Endorser');
        $this->actingAs($user);
    }

    public static function categories(): array
    {
        return [['PR', 'purchase-requests'], ['PO', 'purchase-orders'], ['VCH', 'vouchers']];
    }

    private function procurement(string $category): Procurement
    {
        $procurement = Procurement::factory()->create();
        if ($category !== 'PR') {
            $transaction = Transaction::factory()->create(['procurement_id' => $procurement->id, 'category' => 'PR', 'reference_number' => 'EXISTING-PR-'.$procurement->id]);
            PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);
        }
        if ($category === 'VCH') {
            $transaction = Transaction::factory()->create(['procurement_id' => $procurement->id, 'category' => 'PO', 'reference_number' => 'EXISTING-PO-'.$procurement->id]);
            PurchaseOrder::factory()->create(['transaction_id' => $transaction->id]);
        }

        return $procurement;
    }

    private function payload(string $category, array $overrides = []): array
    {
        $data = ['ref_year' => '2026', 'ref_month' => '10'];
        if ($category === 'PR') {
            $data['fund_type_id'] = (FundType::where('abbreviation', 'GF')->first() ?? FundType::factory()->create(['abbreviation' => 'GF']))->id;
        } elseif ($category === 'PO') {
            $data += ['supplier_id' => Supplier::factory()->create()->id, 'contract_price' => '1250.75'];
        } else {
            $data['payee'] = 'Demo Payee';
        }

        return array_replace($data, $overrides);
    }

    #[DataProvider('categories')]
    public function test_create_estimates_do_not_reserve_a_number(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        $this->travelTo(now()->setDate(2026, 10, 15));
        foreach ([1, 2] as $attempt) {
            $this->get(route("procurements.{$resource}.create", $procurement))->assertOk()
                ->assertInertia(fn ($page) => $page->where('referenceNumbering', ['year' => '2026', 'month' => '10', 'next_number' => '001']));
        }
        $this->assertDatabaseCount('reference_sequences', 0);
    }

    #[DataProvider('categories')]
    public function test_default_creation_allocates_even_when_client_sends_a_stale_or_forced_number(string $category, string $resource): void
    {
        $payload = $this->payload($category, ['ref_number' => '999', 'reference_number' => 'CLIENT-FORCED', 'manual_reference' => false]);
        foreach (['001', '002'] as $expected) {
            $procurement = $this->procurement($category);
            $this->post(route("procurements.{$resource}.store", $procurement), $payload)
                ->assertSessionHasNoErrors()->assertRedirect(route('procurements.show', $procurement));
            $reference = ($category === 'PR' ? 'PR-GF' : $category).'-2026-10-'.$expected;
            $this->assertDatabaseHas('transactions', ['procurement_id' => $procurement->id, 'category' => $category, 'reference_number' => $reference]);
        }
        $this->assertDatabaseHas('reference_sequences', ['category' => $category, 'year' => 2026, 'last_sequence' => 2]);
    }

    public function test_pr_manual_override_is_accepted_at_creation_and_update_preserves_it(): void
    {
        $procurement = $this->procurement('PR');
        $this->post(route('procurements.purchase-requests.store', $procurement), $this->payload('PR', [
            'manual_reference' => true, 'reference_number' => 'MY-OFFICE-999', 'ref_number' => ['bad'],
        ]))->assertSessionHasNoErrors();
        $pr = $procurement->purchaseRequest()->firstOrFail();
        $this->assertSame('MY-OFFICE-999', $pr->transaction->reference_number);
        $fund = FundType::factory()->create();
        $this->put(route('purchase-requests.update', $pr), [
            'fund_type_id' => $fund->id, 'reference_number' => 'FORCED', 'ref_number' => '888', 'ref_year' => '2027', 'is_continuation' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('MY-OFFICE-999', $pr->transaction->fresh()->reference_number);
        $this->assertFalse($pr->transaction->fresh()->is_continuation);
        $this->assertSame($fund->id, $pr->fresh()->fund_type_id);
    }

    public static function overridableCategories(): array
    {
        return self::categories();
    }

    #[DataProvider('overridableCategories')]
    public function test_office_reference_override_is_explicit_and_unique_including_deleted_documents(string $category, string $resource): void
    {
        $first = $this->procurement($category);
        $payload = $this->payload($category, ['manual_reference' => true, 'reference_number' => 'OFFICE/2026/0042', 'ref_year' => '', 'ref_month' => '']);
        $this->post(route("procurements.{$resource}.store", $first), $payload)->assertSessionHasNoErrors();
        $transaction = $first->transactions()->where('category', $category)->firstOrFail();
        $this->assertSame('OFFICE/2026/0042', $transaction->reference_number);
        $this->assertDatabaseCount('reference_sequences', 0);
        $transaction->delete();
        $second = $this->procurement($category);
        $this->post(route("procurements.{$resource}.store", $second), $payload)->assertSessionHasErrors('reference_number');
        $this->assertSame(0, $second->transactions()->where('category', $category)->count());
    }

    #[DataProvider('overridableCategories')]
    public function test_recognized_manual_reference_advances_the_automatic_high_water_mark(string $category, string $resource): void
    {
        $first = $this->procurement($category);
        $prefix = $category === 'PR' ? 'PR-GF' : $category;
        $payload = $this->payload($category, ['manual_reference' => true, 'reference_number' => $prefix.'-2026-01-145']);
        $this->post(route("procurements.{$resource}.store", $first), $payload)->assertSessionHasNoErrors();
        $second = $this->procurement($category);
        $this->post(route("procurements.{$resource}.store", $second), $this->payload($category))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['procurement_id' => $second->id, 'category' => $category, 'reference_number' => $prefix.'-2026-10-146']);
    }

    #[DataProvider('categories')]
    public function test_failed_save_rolls_back_sequence_and_document(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        $this->mock(WorkflowAssignmentService::class, function ($mock) {
            $mock->shouldReceive('assignWorkflow')->once()->andThrow(new \RuntimeException('Simulated save failure'));
        });
        $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category))->assertSessionHas('error');
        $this->assertSame(0, $procurement->transactions()->where('category', $category)->count());
        $this->assertDatabaseCount('reference_sequences', 0);
        $this->assertSame('001', app(ReferenceNumberService::class)->previewSequence($category, 2026));
    }

    #[DataProvider('overridableCategories')]
    public function test_manual_duplicate_race_returns_a_field_error_without_partial_save(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        // Simulate a competing insert after form validation, before our insert.
        $this->partialMock(ReferenceNumberService::class, function ($mock) use ($category) {
            $mock->shouldReceive('trackManualReference')->once()->andReturnUsing(function () use ($category) {
                Transaction::factory()->create(['category' => $category, 'reference_number' => 'RACE-OFFICE-REF']);
            });
        });
        $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category, [
            'manual_reference' => true, 'reference_number' => 'RACE-OFFICE-REF',
        ]))->assertSessionHasErrors('reference_number');
        $this->assertSame(0, $procurement->transactions()->where('category', $category)->count());
    }

    #[DataProvider('categories')]
    public function test_invalid_auto_dates_do_not_allocate_and_viewers_remain_forbidden(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        $payload = $this->payload($category, ['ref_year' => '26', 'ref_month' => '13']);
        $this->post(route("procurements.{$resource}.store", $procurement), $payload)->assertSessionHasErrors(['ref_year', 'ref_month']);
        $this->assertDatabaseCount('reference_sequences', 0);
        $viewer = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $viewer->assignRole('Viewer');
        $this->actingAs($viewer)->post(route("procurements.{$resource}.store", $procurement), $this->payload($category))->assertForbidden();
        $this->assertDatabaseCount('reference_sequences', 0);
    }

    public function test_po_update_accepts_external_reference_and_preserves_existing_workflow_and_continuation(): void
    {
        $transaction = Transaction::factory()->create(['category' => 'PO', 'reference_number' => 'CONT-PO-2025-12-009', 'is_continuation' => true]);
        $po = PurchaseOrder::factory()->create(['transaction_id' => $transaction->id]);
        $this->put(route('purchase-orders.update', $po), ['reference_number' => 'OFFICE-PO-77', 'supplier_id' => $po->supplier_id, 'contract_price' => '345.50'])->assertSessionHasNoErrors();
        $this->assertSame('OFFICE-PO-77', $transaction->fresh()->reference_number);
        $this->assertTrue($transaction->fresh()->is_continuation);
        $this->assertSame('345.50', $po->fresh()->contract_price);
    }

    #[DataProvider('categories')]
    public function test_valid_numeric_year_and_month_values_are_accepted(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category, [
            'ref_year' => 2026, 'ref_month' => 10,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('procurements.show', $procurement));
        $this->assertSame(1, $procurement->transactions()->where('category', $category)->count());
    }

    #[DataProvider('overridableCategories')]
    public function test_manual_mode_requires_a_nonempty_bounded_reference(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        foreach (['', str_repeat('x', 51)] as $reference) {
            $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category, [
                'manual_reference' => true, 'reference_number' => $reference,
            ]))->assertSessionHasErrors('reference_number');
        }
        $this->assertDatabaseCount('reference_sequences', 0);
    }

    public function test_po_override_using_pr_prefix_does_not_block_future_automatic_prs(): void
    {
        $poProcurement = $this->procurement('PO');
        $this->post(route('procurements.purchase-orders.store', $poProcurement), $this->payload('PO', [
            'manual_reference' => true, 'reference_number' => 'pr-gf-2026-10-001',
        ]))->assertSessionHasNoErrors();
        $prProcurement = $this->procurement('PR');
        $this->post(route('procurements.purchase-requests.store', $prProcurement), $this->payload('PR'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['procurement_id' => $prProcurement->id, 'reference_number' => 'PR-GF-2026-10-002']);
        $this->assertDatabaseHas('reference_sequences', ['category' => 'PR', 'year' => 2026, 'last_sequence' => 2]);
    }

    #[DataProvider('categories')]
    public function test_automatic_references_require_the_current_server_year(string $category, string $resource): void
    {
        foreach (['2025', '2027'] as $year) {
            $procurement = $this->procurement($category);
            $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category, [
                'ref_year' => $year,
            ]))->assertSessionHasErrors('ref_year');
            $this->assertSame(0, $procurement->transactions()->where('category', $category)->count());
        }
        $this->assertDatabaseCount('reference_sequences', 0);
    }

    #[DataProvider('categories')]
    public function test_manual_references_can_keep_an_office_issued_historical_year(string $category, string $resource): void
    {
        $procurement = $this->procurement($category);
        $this->post(route("procurements.{$resource}.store", $procurement), $this->payload($category, [
            'manual_reference' => true, 'reference_number' => 'OFFICE-2025-HISTORICAL', 'ref_year' => '2025',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', [
            'procurement_id' => $procurement->id, 'category' => $category, 'reference_number' => 'OFFICE-2025-HISTORICAL',
        ]);
    }
}
