<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcurementFieldType;
use Database\Factories\ProcurementFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProcurementField extends Model
{
    /** @use HasFactory<ProcurementFieldFactory> */
    use HasFactory;

    public const EDITABLE_STAGE_DRAFT = 'draft';

    public const EDITABLE_STAGE_REVIEW = 'review';

    public const EDITABLE_STAGE_APPROVAL = 'approval';

    public const EDITABLE_STAGES = [
        self::EDITABLE_STAGE_DRAFT,
        self::EDITABLE_STAGE_REVIEW,
        self::EDITABLE_STAGE_APPROVAL,
    ];

    protected $fillable = [
        'category_id',
        'key',
        'label',
        'field_type',
        'sort_order',
        'is_required',
        'options',
        'default_value',
        'min_value',
        'max_value',
        'visibility_conditions',
        'editable_stage',
        'version',
        'is_active',
        'activated_at',
        'deactivated_at',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_required' => false,
        'editable_stage' => self::EDITABLE_STAGE_DRAFT,
        'version' => 1,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            try {
                ProcurementFieldType::from((string) ($field->getAttributes()['field_type'] ?? ''));
            } catch (\ValueError) {
                throw ValidationException::withMessages([
                    'field_type' => 'The selected field type is not supported.',
                ]);
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $field->key)) {
                throw ValidationException::withMessages([
                    'key' => 'The field key must start with a letter and contain only lowercase letters, numbers, and underscores.',
                ]);
            }

            if (! in_array($field->editable_stage, self::EDITABLE_STAGES, true)) {
                throw ValidationException::withMessages([
                    'editable_stage' => 'The selected editability stage is not supported.',
                ]);
            }

            if ($field->exists && $field->isDirty(self::definitionColumns()) && ! $field->isDirty('version')) {
                $field->version = ((int) $field->getOriginal('version')) + 1;
            }

            if ($field->is_active) {
                $field->deactivated_at = null;
                $field->activated_at ??= now();
            } else {
                $field->activated_at = null;
                $field->deactivated_at ??= now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'field_type' => ProcurementFieldType::class,
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'options' => 'array',
            'default_value' => 'json',
            'visibility_conditions' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
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

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function definitionSnapshot(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'field_type' => $this->field_type instanceof ProcurementFieldType
                ? $this->field_type->value
                : (string) $this->field_type,
            'sort_order' => (int) $this->sort_order,
            'is_required' => (bool) $this->is_required,
            'options' => $this->options,
            'default_value' => $this->default_value,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'visibility_conditions' => $this->visibility_conditions,
            'editable_stage' => $this->editable_stage,
            'version' => (int) $this->version,
        ];
    }

    public function deactivate(): bool
    {
        return $this->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ])->save();
    }

    /** @return list<string> */
    private static function definitionColumns(): array
    {
        return [
            'key',
            'label',
            'field_type',
            'sort_order',
            'is_required',
            'options',
            'default_value',
            'min_value',
            'max_value',
            'visibility_conditions',
            'editable_stage',
            'is_active',
        ];
    }

    /** @return array<int, mixed>|null */
    public function getVisibilityConditionAttribute(): ?array
    {
        return $this->visibility_conditions;
    }

    public function getOrderAttribute(): int
    {
        return (int) $this->sort_order;
    }
}
