<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('email')->constrained('offices')->onDelete('set null');
            $table->boolean('is_active')->default(true)->after('office_id');

            // Indexes
            $table->index('office_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropIndex(['office_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['office_id', 'is_active']);
        });
    }
};
