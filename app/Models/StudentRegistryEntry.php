<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\StudentRegistryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $student_number
 * @property string $name
 * @property string $course
 * @property int|null $year_graduated
 * @property int|null $claimed_by_user_id
 * @property CarbonImmutable|null $claimed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $claimedBy
 */
#[Fillable(['student_number', 'name', 'course', 'year_graduated'])]
class StudentRegistryEntry extends Model
{
    /** @use HasFactory<StudentRegistryEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_graduated' => 'integer',
            'claimed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the account that registered against this roster entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    /**
     * Determine whether an account has already been opened against the entry.
     */
    public function isClaimed(): bool
    {
        return $this->claimed_by_user_id !== null;
    }

    /**
     * Scope the query to entries no account has claimed yet.
     *
     * @param  Builder<StudentRegistryEntry>  $query
     */
    #[Scope]
    protected function unclaimed(Builder $query): void
    {
        $query->whereNull('claimed_by_user_id');
    }

    /**
     * Scope the query to entries an account has already claimed.
     *
     * @param  Builder<StudentRegistryEntry>  $query
     */
    #[Scope]
    protected function claimed(Builder $query): void
    {
        $query->whereNotNull('claimed_by_user_id');
    }

    /**
     * Scope the query to entries matching a student number, name, or course.
     *
     * @param  Builder<StudentRegistryEntry>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->where('student_number', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%')
                ->orWhere('course', 'like', '%'.$term.'%');
        });
    }
}
