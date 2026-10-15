<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionTaken;
use App\Models\Office;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\EndorsementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentQrHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function document(string $category = 'PR'): array
    {
        $source = Office::factory()->create();
        $target = Office::factory()->create();
        $workflow = Workflow::factory()->create(['category' => $category]);
        $first = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'office_id' => $source->id, 'step_order' => 1, 'is_final_step' => false]);
        $final = WorkflowStep::factory()->create(['workflow_id' => $workflow->id, 'office_id' => $target->id, 'step_order' => 2, 'is_final_step' => true]);
        $sender = User::factory()->create(['office_id' => $source->id]);
        $sender->assignRole('Endorser');
        $receiver = User::factory()->create(['office_id' => $target->id]);
        $receiver->assignRole('Endorser');
        $transaction = Transaction::factory()->create([
            'category' => $category, 'reference_number' => $category.'-2026-10-001', 'status' => 'Created',
            'workflow_id' => $workflow->id, 'current_step_id' => $first->id,
            'current_office_id' => $source->id, 'received_at' => null,
        ]);
        $action = ActionTaken::factory()->create(['is_active' => true]);

        return compact('source', 'target', 'sender', 'receiver', 'transaction', 'action', 'first', 'final');
    }

    private function incoming(array $setup): void
    {
        app(EndorsementService::class)->endorse($setup['transaction'], $setup['sender'], $setup['action']->id,
            $setup['target']->id, $setup['transaction']->reference_number);
    }

    public static function invalidScans(): array
    {
        return [
            'missing' => [null], 'empty' => [''], 'spaces' => ['   '], 'other document' => ['PO-2026-10-999'],
            'lowercase' => ['pr-2026-10-001'], 'leading space' => [' PR-2026-10-001'],
            'trailing space' => ['PR-2026-10-001 '], 'leading newline' => ["\nPR-2026-10-001"],
            'embedded newline' => ["PR-2026\n-10-001"], 'number' => [123], 'array' => [['PR-2026-10-001']],
            'too long' => [str_repeat('A', 256)],
        ];
    }

    #[DataProvider('invalidScans')]
    public function test_endorse_requires_the_exact_scanned_reference(mixed $scan): void
    {
        $s = $this->document();
        $payload = ['action_taken_id' => $s['action']->id, 'to_office_id' => $s['target']->id];
        if ($scan !== null) {
            $payload['scanned_reference'] = $scan;
        }
        $this->actingAs($s['sender'])->post(route('transactions.endorse.store', $s['transaction']), $payload)
            ->assertSessionHasErrors('scanned_reference');
        $this->assertDatabaseCount('transaction_actions', 0);
        $this->assertSame('Created', $s['transaction']->fresh()->status);
        $this->assertSame($s['source']->id, $s['transaction']->fresh()->current_office_id);
    }

    #[DataProvider('invalidScans')]
    public function test_receive_requires_the_exact_scanned_reference(mixed $scan): void
    {
        $s = $this->document();
        $this->incoming($s);
        $payload = $scan === null ? [] : ['scanned_reference' => $scan];
        $this->actingAs($s['receiver'])->post(route('transactions.receive.store', $s['transaction']), $payload)
            ->assertSessionHasErrors('scanned_reference');
        $this->assertDatabaseCount('transaction_actions', 1);
        $this->assertNull($s['transaction']->fresh()->received_at);
        $this->assertSame($s['first']->id, $s['transaction']->fresh()->current_step_id);
    }

    public static function categories(): array
    {
        return [['PR'], ['PO'], ['VCH']];
    }

    #[DataProvider('categories')]
    public function test_scanned_handoffs_keep_existing_workflow_and_audit_behavior(string $category): void
    {
        $s = $this->document($category);
        $reference = $s['transaction']->reference_number;
        $this->actingAs($s['sender'])->post(route('transactions.endorse.store', $s['transaction']), [
            'scanned_reference' => $reference."\r\n", 'action_taken_id' => $s['action']->id,
            'to_office_id' => $s['target']->id, 'notes' => '  Sent paper document  ',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $s['transaction']->fresh()->status);
        $this->assertSame($s['first']->id, $s['transaction']->fresh()->current_step_id);
        $this->assertDatabaseHas('transaction_actions', ['transaction_id' => $s['transaction']->id,
            'action_type' => 'endorse', 'notes' => 'Sent paper document', 'is_out_of_workflow' => false]);

        $this->actingAs($s['receiver'])->get(route('transactions.receive.create', $s['transaction']))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Transactions/Receive')
            ->where('transaction.reference_number', $reference)
            ->where('transaction.current_office.id', $s['target']->id)
            ->where('fromOffice.id', $s['source']->id)->has('workflowSteps', 2));
        $this->post(route('transactions.receive.store', $s['transaction']), [
            'scanned_reference' => $reference."\n", 'notes' => '  Paper received  ',
        ])->assertRedirect(route('transactions.pending'))->assertSessionHasNoErrors();
        $this->assertNotNull($s['transaction']->fresh()->received_at);
        $this->assertSame($s['final']->id, $s['transaction']->fresh()->current_step_id);
        $this->assertDatabaseHas('transaction_actions', ['transaction_id' => $s['transaction']->id,
            'action_type' => 'receive', 'notes' => 'Paper received', 'to_user_id' => $s['receiver']->id]);
    }

    public function test_receive_page_preserves_role_and_office_restrictions(): void
    {
        $s = $this->document();
        $this->incoming($s);
        $this->actingAs($s['sender'])->get(route('transactions.receive.create', $s['transaction']))->assertForbidden();
        $s['receiver']->syncRoles(['Viewer']);
        $this->actingAs($s['receiver'])->get(route('transactions.receive.create', $s['transaction']))->assertForbidden();
    }

    public function test_inactive_or_unmapped_sso_office_cannot_confirm_a_handoff(): void
    {
        $s = $this->document();
        $s['source']->update(['sso_id' => null]);
        $this->actingAs($s['sender'])->post(route('transactions.endorse.store', $s['transaction']), [
            'scanned_reference' => $s['transaction']->reference_number,
            'action_taken_id' => $s['action']->id, 'to_office_id' => $s['target']->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('transaction_actions', 0);
    }

    public function test_inactive_sso_destination_is_rejected_without_changing_state(): void
    {
        $s = $this->document();
        $s['target']->update(['is_active' => false]);
        $this->actingAs($s['sender'])->post(route('transactions.endorse.store', $s['transaction']), [
            'scanned_reference' => $s['transaction']->reference_number,
            'action_taken_id' => $s['action']->id, 'to_office_id' => $s['target']->id,
        ])->assertSessionHasErrors('to_office_id');
        $this->assertDatabaseCount('transaction_actions', 0);
    }

    public function test_service_rechecks_reference_after_loading_a_stale_model(): void
    {
        $s = $this->document();
        Transaction::whereKey($s['transaction']->id)->update(['reference_number' => 'EXTERNAL-CHANGED']);
        try {
            app(EndorsementService::class)->endorse($s['transaction'], $s['sender'], $s['action']->id,
                $s['target']->id, 'PR-2026-10-001');
            $this->fail('A stale reference must not be accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scanned_reference', $e->errors());
        }
        $this->assertDatabaseCount('transaction_actions', 0);
    }

    public function test_service_rechecks_office_and_status_under_the_lock(): void
    {
        $s = $this->document();
        Transaction::whereKey($s['transaction']->id)->update(['status' => 'On Hold']);
        try {
            app(EndorsementService::class)->endorse($s['transaction'], $s['sender'], $s['action']->id,
                $s['target']->id, $s['transaction']->reference_number);
            $this->fail('A stale state must not be accepted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('transaction_actions', 0);
        }
    }

    public function test_a_repeated_receive_cannot_create_another_action(): void
    {
        $s = $this->document();
        $this->incoming($s);
        $stale = $s['transaction']->fresh();
        $service = app(EndorsementService::class);
        $service->receive($s['transaction'], $s['receiver'], $s['transaction']->reference_number);
        try {
            $service->receive($stale, $s['receiver'], $stale->reference_number);
            $this->fail('A second receive must be rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('transaction_actions', 2);
        }
    }

    public function test_bulk_requires_a_matching_scan_for_every_selected_document(): void
    {
        $s = $this->document();
        $this->incoming($s);
        foreach ([[], ['scanned_references' => []], ['scanned_references' => [$s['transaction']->id => 'WRONG']],
            ['scanned_references' => [$s['transaction']->id => $s['transaction']->reference_number.' ']]] as $payload) {
            $this->actingAs($s['receiver'])->post(route('transactions.receive.bulk'), array_merge([
                'transaction_ids' => [$s['transaction']->id],
            ], $payload))->assertSessionHasErrors();
            $this->assertNull($s['transaction']->fresh()->received_at);
        }
        $this->assertDatabaseCount('transaction_actions', 1);
    }

    public function test_bulk_does_not_allow_duplicate_ids_or_other_offices(): void
    {
        $s = $this->document();
        $this->incoming($s);
        $this->actingAs($s['receiver'])->post(route('transactions.receive.bulk'), [
            'transaction_ids' => [$s['transaction']->id, $s['transaction']->id],
            'scanned_references' => [$s['transaction']->id => $s['transaction']->reference_number],
        ])->assertSessionHasErrors('transaction_ids.0');
        $this->actingAs($s['sender'])->post(route('transactions.receive.bulk'), [
            'transaction_ids' => [$s['transaction']->id],
            'scanned_references' => [$s['transaction']->id => $s['transaction']->reference_number],
        ])->assertRedirect(route('transactions.pending'));
        $this->assertNull($s['transaction']->fresh()->received_at);
        $this->assertDatabaseCount('transaction_actions', 1);
    }

    public function test_direct_bulk_service_without_scan_never_receives_a_document(): void
    {
        $s = $this->document();
        $this->incoming($s);
        $result = app(EndorsementService::class)->receiveBulk([$s['transaction']->id], $s['receiver'], []);
        $this->assertSame([], $result['success']);
        $this->assertArrayHasKey($s['transaction']->id, $result['failed']);
        $this->assertNull($s['transaction']->fresh()->received_at);
    }
}
