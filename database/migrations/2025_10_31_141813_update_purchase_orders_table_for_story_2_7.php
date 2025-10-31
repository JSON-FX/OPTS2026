<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Story 2.7 - Update purchase_orders table to match simplified schema from Story 2.6/2.7.
     * Removes old fields and adds contract_price for manual reference number implementation.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Drop old Story 2.1 columns
            $table->dropForeign(['purchase_request_id']);
            $table->dropForeign(['fund_type_id']);
            $table->dropColumn([
                'purchase_request_id',
                'particulars',
                'fund_type_id',
                'total_cost',
                'date_of_po',
                'delivery_date',
                'delivery_term',
                'payment_term',
                'amount_in_words',
                'mode_of_procurement',
            ]);

            // Add new Story 2.6/2.7 column
            $table->decimal('contract_price', 15, 2)->after('supplier_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('contract_price');

            // Restore old columns
            $table->foreignId('purchase_request_id')
                ->after('supplier_address')
                ->constrained('purchase_requests')
                ->restrictOnDelete();
            $table->text('particulars')->after('purchase_request_id');
            $table->foreignId('fund_type_id')
                ->after('particulars')
                ->constrained('fund_types')
                ->restrictOnDelete();
            $table->decimal('total_cost', 15, 2)->unsigned()->after('fund_type_id');
            $table->date('date_of_po')->after('total_cost');
            $table->date('delivery_date')->nullable()->after('date_of_po');
            $table->unsignedSmallInteger('delivery_term')->nullable()->after('delivery_date');
            $table->unsignedSmallInteger('payment_term')->nullable()->after('delivery_term');
            $table->text('amount_in_words')->after('payment_term');
            $table->string('mode_of_procurement', 100)->after('amount_in_words');
        });
    }
};
