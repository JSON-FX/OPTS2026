<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('migration_imports', function (Blueprint $table) {
            $table->boolean('exclude_orphans')->default(true)->after('dry_run_report');
        });
    }

    public function down(): void
    {
        Schema::table('migration_imports', function (Blueprint $table) {
            $table->dropColumn('exclude_orphans');
        });
    }
};
