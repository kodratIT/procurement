<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PurchaseRequestFieldValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestFieldValue extends Model
{
    /** @use HasFactory<PurchaseRequestFieldValueFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'field_id',
        'field_key',
        'field_label',
        'field_type',
        'field_version',
        'definition_snapshot',
        'value',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $value): void {
            if ($value->field_id === null) {
                return;
            }

            $field = ProcurementField::query()->findOrFail($value->field_id);
            $snapshot = $value->definition_snapshot ?? $field->definitionSnapshot();

            $value->field_key ??= $snapshot['key'] ?? $field->key;
            $value->field_label ??= $snapshot['label'] ?? $field->label;
            $value->field_type ??= $snapshot['field_type'] ?? $field->field_type->value;
            $value->field_version ??= $snapshot['version'] ?? $field->version;
            $value->definition_snapshot = $snapshot;
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'field_id' => 'integer',
            'field_version' => 'integer',
            'definition_snapshot' => 'array',
            'value' => 'json',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ProcurementField::class, 'field_id');
    }

    public static function fromField(
        PurchaseRequest $purchaseRequest,
        ProcurementField $field,
        mixed $value,
    ): self {
        return new self([
            'purchase_request_id' => $purchaseRequest->getKey(),
            'field_id' => $field->getKey(),
            'field_key' => $field->key,
            'field_label' => $field->label,
            'field_type' => $field->field_type->value,
            'field_version' => $field->version,
            'definition_snapshot' => $field->definitionSnapshot(),
            'value' => $value,
        ]);
    }
}
