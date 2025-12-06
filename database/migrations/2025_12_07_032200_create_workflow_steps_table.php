<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 3.1: Workflow Schema & Model Foundation
 *
 * Creates the workflow_steps table which defines ordered steps within a workflow.
 * Each step represents an office in the transaction routing sequence with:
 * - step_order: Position in workflow sequence (1, 2, 3...)
 * - expected_days: SLA days to complete this step
 * - is_final_step: Boolean marking the last step in workflow
 *
 * Constraints:
 * - Each workflow can only have one entry per step_order (unique constraint)
 * - Each office can only appear once per workflow (unique constraint)
 * - Cascade delete on workflow (steps deleted when workflow deleted)
 * - Restrict delete on office (cannot delete office used in a workflow step)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('workflows')
                ->cascadeOnDelete();
            $table->foreignId('office_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->unsignedInteger('step_order');
            $table->unsignedInteger('expected_days');
            $table->boolean('is_final_step')->default(false);
            $table->timestamps();

            // Unique indexes per AC requirements
            $table->unique(['workflow_id', 'step_order']);
            $table->unique(['workflow_id', 'office_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
