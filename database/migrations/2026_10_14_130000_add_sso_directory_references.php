<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('type', 100)->nullable()->change();
            $table->unsignedBigInteger('sso_id')->nullable()->unique();
            $table->timestamp('sso_synced_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('sso_role')->nullable();
            $table->unsignedBigInteger('sso_position_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sso_role', 'sso_position_id']);
        });
        Schema::table('offices', function (Blueprint $table) {
            $table->dropUnique(['sso_id']);
            $table->dropColumn(['sso_id', 'sso_synced_at']);
        });
        // Keep email/type nullable: SSO does not provide these reference fields.
    }
};
