<?php

namespace App\Enums;

enum NotificationType: string
{
    case RequestSubmitted = 'request_submitted';
    case RequestReceived = 'request_received';
    case RequestStatusChanged = 'request_status_changed';
    case RequestRejected = 'request_rejected';
    case AppointmentBooked = 'appointment_booked';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentCancelled = 'appointment_cancelled';
    case AppointmentReminder = 'appointment_reminder';

    /**
     * Get the human-readable label for the notification type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RequestSubmitted => __('Request submitted'),
            self::RequestReceived => __('New request received'),
            self::RequestStatusChanged => __('Request status updated'),
            self::RequestRejected => __('Request rejected'),
            self::AppointmentBooked => __('Appointment booked'),
            self::AppointmentConfirmed => __('Appointment confirmed'),
            self::AppointmentCancelled => __('Appointment cancelled'),
            self::AppointmentReminder => __('Appointment reminder'),
        };
    }

    /**
     * Get the Heroicon name shown alongside the notification.
     */
    public function icon(): string
    {
        return match ($this) {
            self::RequestSubmitted, self::RequestReceived => 'document-plus',
            self::RequestStatusChanged => 'arrow-path',
            self::RequestRejected => 'x-circle',
            self::AppointmentBooked, self::AppointmentConfirmed => 'calendar-days',
            self::AppointmentCancelled => 'calendar',
            self::AppointmentReminder => 'bell-alert',
        };
    }

    /**
     * Get the Flux badge colour representing the notification type.
     */
    public function color(): string
    {
        return match ($this) {
            self::RequestSubmitted, self::RequestReceived => 'blue',
            self::RequestStatusChanged => 'teal',
            self::RequestRejected, self::AppointmentCancelled => 'red',
            self::AppointmentBooked, self::AppointmentConfirmed => 'green',
            self::AppointmentReminder => 'amber',
        };
    }

    /**
     * Determine whether the notification is addressed to registrar personnel.
     */
    public function isForStaff(): bool
    {
        return $this === self::RequestReceived;
    }
}
