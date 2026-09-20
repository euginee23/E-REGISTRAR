<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The fee is snapshotted onto the request when it is submitted, so that
     * repricing a document later never rewrites what a student was asked to
     * pay. The receipt number is deliberately not unique: one official
     * receipt often covers several documents claimed in the same visit.
     */
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->string('payment_status', 16)->default('not_required')->after('status');
            $table->decimal('fee_amount', 8, 2)->default(0)->after('payment_status');
            $table->decimal('amount_paid', 8, 2)->nullable()->after('fee_amount');
            $table->string('or_number', 32)->nullable()->after('amount_paid');
            $table->timestamp('paid_at')->nullable()->after('or_number');
            $table->foreignId('recorded_by_user_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();

            $table->index(['payment_status', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropIndex(['payment_status', 'status']);
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn(['payment_status', 'fee_amount', 'amount_paid', 'or_number', 'paid_at']);
        });
    }
};
