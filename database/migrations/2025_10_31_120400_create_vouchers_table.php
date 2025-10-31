<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Story 2.8 - Create vouchers table with simplified schema.
     * Only stores transaction FK and free-text payee field.
     * Reference numbers are auto-generated (unlike PR/PO manual input).
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->string('payee', 255);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
