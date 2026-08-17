<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\StudentProfileValidationRules;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules, StudentProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Public registration always produces a student account. The role is set
     * here rather than taken from the input, so the registration form cannot
     * be used to mint a staff or administrator account. Those are created by
     * an administrator from the user management screen.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            ...$this->studentProfileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::Student,
                'status' => UserStatus::Active,
            ]);

            $enrollmentStatus = EnrollmentStatus::from($validated['enrollment_status']);

            $user->student()->create([
                'student_number' => $validated['student_number'] ?? null,
                'course' => $validated['course'],
                'enrollment_status' => $enrollmentStatus,
                'year_graduated' => $enrollmentStatus->requiresYearGraduated()
                    ? $validated['year_graduated']
                    : null,
                'contact_number' => $validated['contact_number'],
            ]);

            return $user;
        });
    }
}
