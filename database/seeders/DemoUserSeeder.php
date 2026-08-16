<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed one signed-in-ready account per role for local use and demos.
     */
    public function run(): void
    {
        $this->createUser('Registrar Administrator', 'admin@e-registrar.test', UserRole::Administrator);
        $this->createUser('Registrar Staff', 'staff@e-registrar.test', UserRole::RegistrarStaff);

        $student = $this->createUser('Juan Dela Cruz', 'student@e-registrar.test', UserRole::Student);
        $this->createStudentProfile($student, '2022-10231', 'BS Information Technology', EnrollmentStatus::Enrolled, null);

        $alumnus = $this->createUser('Maria Santos', 'alumni@e-registrar.test', UserRole::Student);
        $this->createStudentProfile($alumnus, '2018-00457', 'BS Business Administration', EnrollmentStatus::Alumnus, 2022);
    }

    /**
     * Create or refresh a demo account.
     */
    private function createUser(string $name, string $email, UserRole $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => $role,
                'status' => UserStatus::Active,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * Create or refresh the student profile attached to a demo account.
     */
    private function createStudentProfile(
        User $user,
        string $studentNumber,
        string $course,
        EnrollmentStatus $enrollmentStatus,
        ?int $yearGraduated,
    ): void {
        Student::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'student_number' => $studentNumber,
                'course' => $course,
                'enrollment_status' => $enrollmentStatus,
                'year_graduated' => $yearGraduated,
                'contact_number' => '09171234567',
            ],
        );
    }
}
