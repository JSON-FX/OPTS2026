<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Story 3.3 - Transaction Actions Schema & Models
     * Creates the transaction_actions table to record endorsement history
     * and audit trail for each transaction action.
     */
    public function up(): void
    {
        Schema::create('transaction_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->onDelete('restrict');
            $table->enum('action_type', ['endorse', 'receive', 'complete', 'hold', 'cancel', 'bypass']);
            $table->foreignId('action_taken_id')
                ->nullable()
                ->constrained('action_taken')
                ->onDelete('restrict');
            $table->foreignId('from_office_id')
                ->nullable()
                ->constrained('offices')
                ->onDelete('restrict');
            $table->foreignId('to_office_id')
                ->nullable()
                ->constrained('offices')
                ->onDelete('restrict');
            $table->foreignId('from_user_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('to_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('workflow_step_id')
                ->nullable()
                ->constrained('workflow_steps')
                ->onDelete('restrict');
            $table->boolean('is_out_of_workflow')->default(false);
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for query optimization
            $table->index('transaction_id');
            $table->index('action_type');
            $table->index('from_office_id');
            $table->index('to_office_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_actions');
    }
};
