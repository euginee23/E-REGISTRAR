<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The registry is the roster of students the school actually enrolled. It
     * is what makes a student number a claim that can be checked rather than a
     * free-text field, so an account cannot be opened against someone else's
     * number.
     */
    public function up(): void
    {
        Schema::create('student_registry_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('student_number', 32)->unique();
            $table->string('name');
            $table->string('course', 150);
            $table->year('year_graduated')->nullable();
            // One account per roster entry, enforced by the database rather
            // than the application, the way appointments guard their request.
            $table->foreignId('claimed_by_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_registry_entries');
    }
};
