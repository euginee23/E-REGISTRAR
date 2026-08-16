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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Unique: a request can never hold two appointments, whatever the
            // application does. Rescheduling updates time_slot_id in place.
            $table->foreignId('document_request_id')->unique()->constrained()->cascadeOnDelete();

            $table->foreignId('time_slot_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('reminder_sent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['time_slot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
