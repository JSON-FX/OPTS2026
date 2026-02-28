<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_import_id')
                ->constrained('migration_imports')
                ->cascadeOnDelete();
            $table->string('target_table', 100);
            $table->unsignedBigInteger('target_id');
            $table->string('source_table', 100);
            $table->unsignedBigInteger('source_id');
            $table->json('source_snapshot')->nullable();
            $table->enum('status', ['created', 'skipped', 'failed'])->default('created');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['migration_import_id', 'status']);
            $table->index(['target_table', 'target_id']);
            $table->index(['source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_records');
    }
};
