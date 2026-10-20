<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A fee is either collected before the office starts work or on the day
     * the document is claimed. The timing is snapshotted onto each request,
     * like the fee itself, so changing a document type later never moves the
     * payment gate on requests already in the queue.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->string('payment_timing', 24)->default('before_processing')->after('requires_payment');
        });

        Schema::table('document_requests', function (Blueprint $table): void {
            $table->string('payment_timing', 24)->default('before_processing')->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn('payment_timing');
        });

        Schema::table('document_types', function (Blueprint $table): void {
            $table->dropColumn('payment_timing');
        });
    }
};
