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
     * Adds tracking columns to transactions table for current workflow position
     * and timestamp tracking.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('current_step_id')
                ->nullable()
                ->after('current_user_id')
                ->constrained('workflow_steps')
                ->onDelete('restrict');
            $table->timestamp('received_at')->nullable()->after('current_step_id');
            $table->timestamp('endorsed_at')->nullable()->after('received_at');

            // Indexes for query optimization
            $table->index('current_step_id');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['current_step_id']);
            $table->dropIndex(['current_step_id']);
            $table->dropIndex(['received_at']);
            $table->dropColumn(['current_step_id', 'received_at', 'endorsed_at']);
        });
    }
};
