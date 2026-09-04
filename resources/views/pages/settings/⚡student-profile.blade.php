<?php

use App\Concerns\StudentProfileValidationRules;
use App\Enums\EnrollmentStatus;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Student profile')] class extends Component {
    use StudentProfileValidationRules;

    public string $student_number = '';
    public string $course = '';
    public string $enrollment_status = '';
    public ?int $year_graduated = null;
    public string $contact_number = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $student = $this->student();

        $this->student_number = $student?->student_number ?? '';
        $this->course = $student?->course ?? '';
        $this->enrollment_status = ($student?->enrollment_status ?? EnrollmentStatus::Enrolled)->value;
        $this->year_graduated = $student?->year_graduated;
        $this->contact_number = $student?->contact_number ?? '';
    }

    /**
     * Save the student profile for the currently authenticated user.
     *
     * The profile is created when it is missing, which happens when an
     * administrator sets up the account rather than the student registering.
     */
    public function updateStudentProfile(): void
    {
        $student = $this->student();

        $validated = $this->validate($this->studentProfileRules($student?->id));

        $enrollmentStatus = EnrollmentStatus::from($validated['enrollment_status']);

        $user = Auth::user();

        $user->student()->updateOrCreate([], [
            'student_number' => $validated['student_number'] ?: null,
            'course' => $validated['course'],
            'enrollment_status' => $enrollmentStatus,
            'year_graduated' => $enrollmentStatus->requiresYearGraduated()
                ? $validated['year_graduated']
                : null,
            'contact_number' => $validated['contact_number'],
        ]);

        $user->unsetRelation('student');

        Flux::toast(variant: 'success', text: __('Student profile updated.'));
    }

    /**
     * Get the signed-in user's student profile, if they have one.
     */
    private function student(): ?Student
    {
        return Auth::user()->student;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Student profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Student profile')" :subheading="__('Details the registrar uses to locate your academic records')">
        @if (session('status') === 'student-profile-required')
            <flux:callout variant="warning" icon="exclamation-triangle" class="mt-6" data-test="profile-required-callout">
                <flux:callout.heading>{{ __('Complete your student profile') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('The registrar needs your course and contact details before you can request documents.') }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="updateStudentProfile" class="my-6 w-full space-y-6">
            <flux:input
                wire:model="student_number"
                :label="__('Student number')"
                :description="__('Optional. Helps the registrar locate your records faster.')"
                type="text"
                autocomplete="off"
                data-test="student-number-input"
            />

            <flux:input
                wire:model="course"
                :label="__('Course')"
                type="text"
                required
                data-test="course-input"
            />

            <flux:select
                wire:model.live="enrollment_status"
                :label="__('Enrollment status')"
                required
                data-test="enrollment-status-select"
            >
                @foreach (App\Enums\EnrollmentStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($enrollment_status === App\Enums\EnrollmentStatus::Alumnus->value)
                <flux:input
                    wire:model="year_graduated"
                    :label="__('Year graduated')"
                    type="number"
                    min="1950"
                    :max="date('Y')"
                    required
                    data-test="year-graduated-input"
                />
            @endif

            <flux:input
                wire:model="contact_number"
                :label="__('Contact number')"
                type="tel"
                required
                data-test="contact-number-input"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-student-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
