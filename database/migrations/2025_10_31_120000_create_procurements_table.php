<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the procurements table that anchors procurement records and links them
     * to end-user offices, particular items, and the user who initiated the request.
     * This table represents the root aggregate for all downstream transactions.
     */
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->foreignId('particular_id')
                ->constrained('particulars')
                ->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->decimal('abc_amount', 15, 2)->unsigned();
            $table->date('date_of_entry');
            $table->enum('status', ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'])
                ->default('Created');
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('date_of_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
