<?php

namespace Database\Seeders;

use App\Actions\Slots\GenerateTimeSlots;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    /**
     * Seed appointment slots for the coming weeks.
     */
    public function run(): void
    {
        $today = CarbonImmutable::today();

        app(GenerateTimeSlots::class)($today, $today->addDays(30));
    }
}
