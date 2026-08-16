<?php

namespace App\Models;

use App\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property string $message
 * @property string|null $url
 * @property bool $is_read
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'type', 'message', 'url', 'is_read'])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_read' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'is_read' => 'boolean',
        ];
    }

    /**
     * Get the user the notification was addressed to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return false;
        }

        return $this->forceFill(['is_read' => true])->save();
    }

    /**
     * Scope the query to notifications the recipient has not opened.
     *
     * @param  Builder<Notification>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->where('is_read', false);
    }
}
