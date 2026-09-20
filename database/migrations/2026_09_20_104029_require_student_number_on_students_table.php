<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The registrar identifies every record by student number, so the column
     * becomes mandatory. Profiles created while it was optional are stamped
     * with a placeholder rather than blocking the migration: the placeholder
     * is deliberately conspicuous so the registrar can correct it later.
     */
    public function up(): void
    {
        DB::table('students')
            ->whereNull('student_number')
            ->orderBy('id')
            ->each(function (object $student): void {
                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'student_number' => 'UNKNOWN-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
                    ]);
            });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('student_number', 32)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('student_number', 32)->nullable()->change();
        });
    }
};
