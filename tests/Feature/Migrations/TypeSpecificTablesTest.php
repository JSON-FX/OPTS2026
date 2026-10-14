<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TypeSpecificTablesTest extends TestCase
{
    use RefreshDatabase;

    public static function documentTypes(): array
    {
        return [
            'purchase request' => [PurchaseRequest::class, 'purchase_requests', [
                'id', 'transaction_id', 'fund_type_id', 'created_at', 'updated_at', 'deleted_at',
            ]],
            'purchase order' => [PurchaseOrder::class, 'purchase_orders', [
                'id', 'transaction_id', 'supplier_id', 'supplier_address', 'contract_price', 'created_at', 'updated_at',
            ]],
            'voucher' => [Voucher::class, 'vouchers', [
                'id', 'transaction_id', 'payee', 'created_at', 'updated_at', 'deleted_at',
            ]],
        ];
    }

    public function test_uniqueness_upgrade_can_be_reversed_and_reapplied_without_losing_documents(): void
    {
        $document = PurchaseRequest::factory()->create();
        $upgrade = require database_path('migrations/2026_10_14_140000_enforce_unique_purchase_request_transaction.php');

        $upgrade->down();
        $this->assertFalse(Schema::hasIndex('purchase_requests', ['transaction_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('purchase_requests', ['transaction_id']));
        $upgrade->up();
        $upgrade->up();
        $this->assertTrue(Schema::hasIndex('purchase_requests', ['transaction_id'], 'unique'));
        $this->assertDatabaseHas('purchase_requests', ['id' => $document->id, 'transaction_id' => $document->transaction_id]);
    }

    public function test_uniqueness_upgrade_preserves_existing_duplicates_and_stops(): void
    {
        Schema::table('purchase_requests', function ($table) {
            $table->dropUnique('purchase_requests_one_per_transaction_unique');
        });
        $first = PurchaseRequest::factory()->create();
        PurchaseRequest::factory()->create(['transaction_id' => $first->transaction_id]);
        $upgrade = require database_path('migrations/2026_10_14_140000_enforce_unique_purchase_request_transaction.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resolve duplicate purchase request transaction links');
        try {
            $upgrade->up();
        } finally {
            $this->assertSame(2, DB::table('purchase_requests')->where('transaction_id', $first->transaction_id)->count());
            $this->assertFalse(Schema::hasIndex('purchase_requests', ['transaction_id'], 'unique'));
        }
    }

    #[DataProvider('documentTypes')]
    public function test_current_document_schema_supports_its_model(string $model, string $table, array $columns): void
    {
        $this->assertTrue(Schema::hasColumns($table, $columns));
        $document = $model::factory()->create();
        $this->assertDatabaseHas($table, ['id' => $document->id, 'transaction_id' => $document->transaction_id]);
    }

    #[DataProvider('documentTypes')]
    public function test_only_one_document_can_reference_a_transaction(string $model, string $table, array $columns): void
    {
        $document = $model::factory()->create();

        $this->expectException(QueryException::class);
        $model::factory()->create(['transaction_id' => $document->transaction_id]);
    }

    #[DataProvider('documentTypes')]
    public function test_referenced_transaction_cannot_be_hard_deleted(string $model, string $table, array $columns): void
    {
        $document = $model::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('transactions')->where('id', $document->transaction_id)->delete();
    }
}
