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
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity')->default(5);
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['slot_date', 'start_time']);
            $table->index(['slot_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
