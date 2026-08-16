<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $student_number
 * @property string $course
 * @property EnrollmentStatus $enrollment_status
 * @property int|null $year_graduated
 * @property string $contact_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, DocumentRequest> $documentRequests
 */
#[Fillable(['user_id', 'student_number', 'course', 'enrollment_status', 'year_graduated', 'contact_number'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'enrollment_status' => EnrollmentStatus::Enrolled->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrollment_status' => EnrollmentStatus::class,
            'year_graduated' => 'integer',
        ];
    }

    /**
     * Get the account the student profile belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the document requests submitted by the student.
     *
     * @return HasMany<DocumentRequest, $this>
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /**
     * Determine whether the student has already graduated.
     */
    public function isAlumnus(): bool
    {
        return $this->enrollment_status === EnrollmentStatus::Alumnus;
    }
}
