<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendNotification;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationType;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendAppointmentRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind students about appointments scheduled for tomorrow';

    /**
     * Execute the console command.
     *
     * The reminder_sent_at stamp makes this idempotent, so a crashed or
     * repeated scheduler run never sends a student the same reminder twice.
     */
    public function handle(SendNotification $sendNotification): int
    {
        $tomorrow = CarbonImmutable::today()->addDay();
        $sent = 0;

        Appointment::query()
            ->whereNull('reminder_sent_at')
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->whereHas('timeSlot', fn ($slot) => $slot->whereDate('slot_date', $tomorrow))
            ->with(['timeSlot', 'documentRequest.student.user', 'documentRequest.documentType'])
            ->chunkById(100, function ($appointments) use ($sendNotification, &$sent): void {
                foreach ($appointments as $appointment) {
                    $sendNotification(
                        $appointment->documentRequest->student->user,
                        NotificationType::AppointmentReminder,
                        __('Reminder: you are claiming :document tomorrow, :date at :time.', [
                            'document' => $appointment->documentRequest->display_name,
                            'date' => $appointment->timeSlot->slot_date->format('F j'),
                            'time' => $appointment->timeSlot->label,
                        ]),
                        route('student.appointments.index'),
                    );

                    $appointment->forceFill(['reminder_sent_at' => now()])->save();

                    $sent++;
                }
            });

        $this->info(sprintf(
            '%d reminder(s) sent for %s.',
            $sent,
            $tomorrow->toDateString(),
        ));

        return self::SUCCESS;
    }
}
