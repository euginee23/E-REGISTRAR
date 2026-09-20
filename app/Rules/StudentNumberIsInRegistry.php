<?php

namespace App\Rules;

use App\Actions\Registry\MatchStudentRegistryEntry;
use App\Exceptions\StudentRegistryMismatchException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StudentNumberIsInRegistry implements ValidationRule
{
    /**
     * Create the rule for the name submitted alongside the student number.
     */
    public function __construct(private ?string $name) {}

    /**
     * Validate that the student number belongs to the person registering.
     *
     * This exists so the form can explain the problem in plain words. It is
     * not the guarantee - CreateNewUser re-checks under a row lock, which is
     * what actually prevents two registrations claiming the same number.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('Enter the student number printed on your student ID.'));

            return;
        }

        if ($this->name === null || trim($this->name) === '') {
            $fail(__('Enter your full name as the registrar has it on record.'));

            return;
        }

        try {
            app(MatchStudentRegistryEntry::class)($value, $this->name);
        } catch (StudentRegistryMismatchException $exception) {
            $fail($exception->getMessage());
        }
    }
}
