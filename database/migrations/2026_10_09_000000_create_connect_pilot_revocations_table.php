<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_pilot_revocations', function (Blueprint $table) {
            $table->string('host_session_digest', 64)->primary();
            $table->timestamp('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_pilot_revocations');
    }
};
