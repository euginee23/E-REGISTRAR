<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $processing_days
 * @property bool $requires_custom_name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DocumentRequest> $documentRequests
 */
#[Fillable(['name', 'slug', 'description', 'processing_days', 'requires_custom_name', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, bool|int>
     */
    protected $attributes = [
        'processing_days' => 3,
        'requires_custom_name' => false,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_days' => 'integer',
            'requires_custom_name' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the requests made for this document type.
     *
     * @return HasMany<DocumentRequest, $this>
     */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /**
     * Scope the query to document types students may currently request.
     *
     * @param  Builder<DocumentType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
