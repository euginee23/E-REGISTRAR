<?php

namespace App\Console\Commands;

use App\Models\TimeSlot;
use Illuminate\Console\Command;

class RecountSlotBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slots:recount {--dry-run : Report drift without correcting it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate time slot booking counters from the appointments table';

    /**
     * Execute the console command.
     *
     * The booked_count column is a denormalisation maintained inside the
     * booking transactions. This command is the safety valve: it recomputes
     * the true figure so any drift can be spotted and corrected.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $drifted = 0;

        TimeSlot::query()
            ->chunkById(200, function ($slots) use (&$drifted, $dryRun): void {
                foreach ($slots as $slot) {
                    $actual = $slot->recountBookings();

                    if ($slot->booked_count === $actual) {
                        continue;
                    }

                    $drifted++;

                    $this->line(sprintf(
                        '%s %s: stored %d, actual %d',
                        $slot->slot_date->toDateString(),
                        $slot->start_time,
                        $slot->booked_count,
                        $actual,
                    ));

                    if (! $dryRun) {
                        $slot->forceFill(['booked_count' => $actual])->save();
                    }
                }
            });

        if ($drifted === 0) {
            $this->info('All slot booking counters are accurate.');

            return self::SUCCESS;
        }

        $this->{$dryRun ? 'warn' : 'info'}(sprintf(
            '%d slot(s) %s.',
            $drifted,
            $dryRun ? 'would be corrected' : 'corrected',
        ));

        return self::SUCCESS;
    }
}
