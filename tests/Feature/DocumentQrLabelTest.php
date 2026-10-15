<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentQrLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function categories(): array
    {
        return [
            ['PR', PurchaseRequest::class, 'purchase-requests.show', 'PR-GF-2026-10-008'],
            ['PO', PurchaseOrder::class, 'purchase-orders.show', 'PO/Office-issued-2026-017'],
            ['VCH', Voucher::class, 'vouchers.show', 'VCH-Ñ/2026-00042'],
        ];
    }

    #[DataProvider('categories')]
    public function test_labels_use_the_exact_saved_reference_without_changing_documents(
        string $category, string $model, string $showRoute, string $reference
    ): void {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create(['category' => $category, 'reference_number' => $reference]);
        $entity = $model::factory()->create(['transaction_id' => $transaction->id]);
        $before = $transaction->fresh()->getRawOriginal();

        $this->actingAs($user)->get(route('transactions.qr-label', $transaction))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Transactions/QrLabel')
            ->where('document.category', $category)->where('document.reference', $reference)
            ->where('document.procurementId', $transaction->procurement_id)
            ->where('entityShowRoute.route', $showRoute)->where('entityShowRoute.id', $entity->id));

        $this->assertSame($before, $transaction->fresh()->getRawOriginal());
        $this->assertDatabaseCount('transaction_actions', 0);
        $this->assertDatabaseCount('transaction_status_history', 0);
    }

    public function test_viewer_can_print_a_readable_document_without_processing_permission(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $transaction = Transaction::factory()->create(['category' => 'PR', 'status' => 'Completed']);
        PurchaseRequest::factory()->create(['transaction_id' => $transaction->id]);

        $this->actingAs($viewer)->get(route('transactions.qr-label', $transaction))->assertOk();
        $this->assertFalse($viewer->canProcessTransactions());
    }

    public function test_guest_cannot_read_a_label(): void
    {
        $transaction = Transaction::factory()->create();
        $this->get(route('transactions.qr-label', $transaction))->assertRedirect(route('login'));
    }

    public function test_deleted_and_missing_documents_have_no_printable_label(): void
    {
        $user = User::factory()->create();
        $transaction = Transaction::factory()->create();
        $transaction->delete();

        $this->actingAs($user)->get(route('transactions.qr-label', $transaction))->assertNotFound();
        $this->get(route('transactions.qr-label', 999999))->assertNotFound();
    }
}
