<?php

namespace App\Actions\Fortify;

use App\Actions\Registry\MatchStudentRegistryEntry;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\StudentProfileValidationRules;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\StudentNumberIsInRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules, StudentProfileValidationRules;

    /**
     * Create the action.
     */
    public function __construct(private MatchStudentRegistryEntry $matchStudentRegistryEntry) {}

    /**
     * Validate and create a newly registered user.
     *
     * Public registration always produces a student account. The role is set
     * here rather than taken from the input, so the registration form cannot
     * be used to mint a staff or administrator account. Those are created by
     * an administrator from the user management screen.
     *
     * The student number must match an unclaimed entry on the registrar's
     * roster, which is what stops an account being opened against another
     * student's number. The match is repeated inside the transaction under a
     * row lock, because the validation pass above cannot keep two
     * simultaneous registrations from claiming the same entry.
     *
     * The account is created pending: matching the roster proves the student
     * number exists and is unclaimed, but a person still reviews the account
     * before it may be used.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $studentProfileRules = $this->studentProfileRules();
        $studentProfileRules['student_number'][] = new StudentNumberIsInRegistry($input['name'] ?? null);

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            ...$studentProfileRules,
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $entry = ($this->matchStudentRegistryEntry)(
                $validated['student_number'],
                $validated['name'],
                lock: true,
            );

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::Student,
                'status' => UserStatus::Pending,
            ]);

            $enrollmentStatus = EnrollmentStatus::from($validated['enrollment_status']);

            $user->student()->create([
                'student_number' => $entry->student_number,
                'course' => $validated['course'],
                'enrollment_status' => $enrollmentStatus,
                'year_graduated' => $enrollmentStatus->requiresYearGraduated()
                    ? $validated['year_graduated']
                    : null,
                'contact_number' => $validated['contact_number'],
            ]);

            $entry->forceFill([
                'claimed_by_user_id' => $user->id,
                'claimed_at' => CarbonImmutable::now(),
            ])->save();

            return $user;
        });
    }
}
