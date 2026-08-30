<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementCategoryField extends Model
{
    use HasFactory;

    public const TYPES = ['text', 'number', 'date', 'select', 'file', 'relation'];

    protected $fillable = [
        'category_id', 'key', 'label', 'type', 'is_required', 'sort_order',
        'options', 'visibility', 'relation_config',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            if (! in_array($field->type, self::TYPES, true)) {
                throw new \InvalidArgumentException('Unsupported dynamic field type.');
            }
            if (preg_match('/^[a-z][a-z0-9_]*$/', (string) $field->key) !== 1) {
                throw new \InvalidArgumentException('Field key must be a lowercase snake_case identifier.');
            }
            if ((int) $field->sort_order < 0) {
                throw new \InvalidArgumentException('Field sort order must not be negative.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
            'visibility' => 'array',
            'relation_config' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategory::class, 'category_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PurchaseRequestFieldValue::class, 'field_id');
    }
}
