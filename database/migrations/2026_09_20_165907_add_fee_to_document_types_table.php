<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Some documents carry a fee that has to reach the cashier before the
     * office starts work. The flag is separate from the amount so a document
     * can be defined as chargeable without the fee being mistaken for the
     * reason it is chargeable.
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->decimal('fee', 8, 2)->default(0)->after('processing_days');
            $table->boolean('requires_payment')->default(false)->after('fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->dropColumn(['fee', 'requires_payment']);
        });
    }
};
