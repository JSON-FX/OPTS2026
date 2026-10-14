<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('purchase_requests', ['transaction_id'], 'unique')) {
            return;
        }

        if (DB::table('purchase_requests')->select('transaction_id')->groupBy('transaction_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Resolve duplicate purchase request transaction links before adding the unique constraint.');
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->unique('transaction_id', 'purchase_requests_one_per_transaction_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('purchase_requests', 'purchase_requests_one_per_transaction_unique')) {
            $hasSupportingIndex = collect(Schema::getIndexes('purchase_requests'))->contains(
                fn (array $index) => $index['name'] !== 'purchase_requests_one_per_transaction_unique'
                    && ($index['columns'][0] ?? null) === 'transaction_id'
            );
            if (! $hasSupportingIndex) {
                Schema::table('purchase_requests', function (Blueprint $table) {
                    $table->index('transaction_id', 'purchase_requests_transaction_link_index');
                });
            }
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropUnique('purchase_requests_one_per_transaction_unique');
            });
        }
    }
};
