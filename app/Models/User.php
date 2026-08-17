<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property UserStatus $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student|null $student
 * @property-read Collection<int, Notification> $registrarNotifications
 * @property-read Collection<int, DocumentRequest> $processedRequests
 */
#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default attribute values.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => UserRole::Student->value,
        'status' => UserStatus::Active->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the student profile belonging to the user.
     *
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get the in-app notifications addressed to the user.
     *
     * Named to avoid colliding with the Notifiable trait's own `notifications`
     * relation, which targets Laravel's polymorphic notifications table.
     *
     * @return HasMany<Notification, $this>
     */
    public function registrarNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    /**
     * Get the document requests this user has processed as registrar personnel.
     *
     * @return HasMany<DocumentRequest, $this>
     */
    public function processedRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class, 'processed_by_user_id');
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    /**
     * Determine whether the user is a registrar staff member.
     */
    public function isRegistrarStaff(): bool
    {
        return $this->role === UserRole::RegistrarStaff;
    }

    /**
     * Determine whether the user is a student or alumnus.
     */
    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    /**
     * Determine whether the user works inside the registrar's office.
     */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Count the notifications the user has not yet read.
     */
    public function unreadNotificationsCount(): int
    {
        return $this->registrarNotifications()->unread()->count();
    }

    /**
     * Mark every unread notification for the user as read.
     *
     * @return int The number of notifications marked.
     */
    public function markAllNotificationsRead(): int
    {
        return $this->registrarNotifications()->unread()->update(['is_read' => true]);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
