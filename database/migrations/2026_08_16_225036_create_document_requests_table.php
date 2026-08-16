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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 24)->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Restricted on delete so retiring a document type can never orphan
            // historical requests. Retiring is `is_active = false` instead.
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            $table->string('other_document_name')->nullable();
            $table->string('purpose', 500);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->string('status', 24)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['student_id', 'status']);
            $table->index(['document_type_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
