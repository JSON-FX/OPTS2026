<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_uuid')->nullable()->unique()->after('id');
            $table->string('sso_position')->nullable()->after('email');
            $table->timestamp('last_sso_login_at')->nullable()->after('is_active');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sso_uuid']);
            $table->dropColumn(['sso_uuid', 'sso_position', 'last_sso_login_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
