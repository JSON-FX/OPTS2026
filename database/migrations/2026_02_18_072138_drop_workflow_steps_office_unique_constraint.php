<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the unique constraint on (workflow_id, office_id) from workflow_steps.
 *
 * Offices can now appear multiple times in a workflow (e.g., BAC appears at
 * step 3 and step 5 in the PR workflow; MMO and MTO repeat in VCH workflow).
 * The (workflow_id, step_order) unique constraint is retained since step order
 * must still be unique per workflow.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropUnique(['workflow_id', 'office_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->unique(['workflow_id', 'office_id']);
        });
    }
};
