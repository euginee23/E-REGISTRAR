<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events are deliberately left enabled: document request reference
     * numbers are assigned by an observer, so suppressing events would
     * produce requests with no way to track them.
     */
    public function run(): void
    {
        $this->call(DocumentTypeSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $this->call([
            DemoUserSeeder::class,
            TimeSlotSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
