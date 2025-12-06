<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 3.1: Workflow Schema & Model Foundation
 *
 * Enhances the existing workflows table with additional metadata columns:
 * - description: Optional workflow description text
 * - created_by_user_id: FK to users table tracking who created the workflow
 *
 * This supports the workflow engine that defines how transactions
 * move through offices with ordered steps and expected completion days.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('created_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['created_by_user_id']);
            $table->dropColumn(['description', 'created_by_user_id']);
        });
    }
};
