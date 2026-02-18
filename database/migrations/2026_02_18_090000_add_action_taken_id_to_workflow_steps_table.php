<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('action_taken_id')
                ->nullable()
                ->after('is_final_step')
                ->constrained('action_taken')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['action_taken_id']);
            $table->dropColumn('action_taken_id');
        });
    }
};
