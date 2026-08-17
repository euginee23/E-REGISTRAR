<?php

namespace App\Concerns;

use App\Enums\EnrollmentStatus;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait StudentProfileValidationRules
{
    /**
     * Get the validation rules used to validate student profiles.
     *
     * @return array<string, array<int, ValidationRule|Enum|array<mixed>|string>>
     */
    protected function studentProfileRules(?int $studentId = null): array
    {
        return [
            'student_number' => $this->studentNumberRules($studentId),
            'course' => $this->courseRules(),
            'enrollment_status' => $this->enrollmentStatusRules(),
            'year_graduated' => $this->yearGraduatedRules(),
            'contact_number' => $this->contactNumberRules(),
        ];
    }

    /**
     * Get the validation rules used to validate student numbers.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function studentNumberRules(?int $studentId = null): array
    {
        return [
            'nullable',
            'string',
            'max:32',
            $studentId === null
                ? Rule::unique(Student::class, 'student_number')
                : Rule::unique(Student::class, 'student_number')->ignore($studentId),
        ];
    }

    /**
     * Get the validation rules used to validate courses.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function courseRules(): array
    {
        return ['required', 'string', 'max:150'];
    }

    /**
     * Get the validation rules used to validate enrollment status.
     *
     * @return array<int, ValidationRule|Enum|array<mixed>|string>
     */
    protected function enrollmentStatusRules(): array
    {
        return ['required', Rule::enum(EnrollmentStatus::class)];
    }

    /**
     * Get the validation rules used to validate graduation years.
     *
     * A year is required from alumni and meaningless for enrolled students.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function yearGraduatedRules(): array
    {
        return [
            'nullable',
            'required_if:enrollment_status,'.EnrollmentStatus::Alumnus->value,
            'integer',
            'min:1950',
            'max:'.date('Y'),
        ];
    }

    /**
     * Get the validation rules used to validate contact numbers.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function contactNumberRules(): array
    {
        return ['required', 'string', 'max:32'];
    }
}
