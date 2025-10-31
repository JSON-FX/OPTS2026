<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the transactions table which captures PR/PO/VCH records tied to a procurement,
     * current routing information, and workflow references. Type-specific attributes live
     * in dedicated tables linked via one-to-one relationships.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements')
                ->restrictOnDelete();
            $table->enum('category', ['PR', 'PO', 'VCH']);
            $table->string('reference_number', 50);
            $table->enum('status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'])
                ->default('Created');
            $table->foreignId('workflow_id')
                ->nullable()
                ->constrained('workflows')
                ->restrictOnDelete();
            $table->foreignId('current_office_id')
                ->nullable()
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('current_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('reference_number');
            $table->index('procurement_id');
            $table->index('category');
            $table->index('status');
            $table->index(['procurement_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
