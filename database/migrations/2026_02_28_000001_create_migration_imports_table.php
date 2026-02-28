<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('batch_id', 50)->unique();
            $table->string('temp_database', 100)->nullable();
            $table->enum('status', [
                'pending', 'importing', 'analyzing', 'dry_run',
                'migrating', 'completed', 'failed', 'rolled_back',
            ])->default('pending');
            $table->unsignedInteger('total_source_records')->default(0);
            $table->unsignedInteger('migrated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('mapping_data')->nullable();
            $table->json('dry_run_report')->nullable();
            $table->json('validation_report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('imported_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_imports');
    }
};
