<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the procurement_status_history table to capture immutable audit trails
     * for procurement status transitions while retaining referential integrity.
     */
    public function up(): void
    {
        Schema::create('procurement_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements')
                ->cascadeOnDelete();
            $table->enum('old_status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'])
                ->nullable();
            $table->enum('new_status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled']);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['procurement_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_status_history');
    }
};

