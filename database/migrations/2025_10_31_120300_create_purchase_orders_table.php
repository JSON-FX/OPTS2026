<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the purchase_orders table to capture PO-specific snapshot data while
     * retaining shared workflow metadata on the transactions table.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->unique()
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->text('supplier_address');
            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->restrictOnDelete();
            $table->text('particulars');
            $table->foreignId('fund_type_id')
                ->constrained('fund_types')
                ->restrictOnDelete();
            $table->decimal('total_cost', 15, 2)->unsigned();
            $table->date('date_of_po');
            $table->date('delivery_date')->nullable();
            $table->unsignedSmallInteger('delivery_term')->nullable();
            $table->unsignedSmallInteger('payment_term')->nullable();
            $table->text('amount_in_words');
            $table->string('mode_of_procurement', 100);
            $table->timestamps();

            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
