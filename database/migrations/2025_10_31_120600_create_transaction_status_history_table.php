<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the transaction_status_history table to log workflow transitions for
     * each transaction across PR/PO/VCH categories with cascading cleanup.
     */
    public function up(): void
    {
        Schema::create('transaction_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->enum('old_status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'])
                ->nullable();
            $table->enum('new_status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled']);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_status_history');
    }
};

