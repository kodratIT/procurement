<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleShipmentCondition;
use Database\Factories\SampleShipmentReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

final class SampleShipmentReceipt extends Model
{
    /** @use HasFactory<SampleShipmentReceiptFactory> */
    use HasFactory;

    public const DISPOSITION_RETURNED = 'returned';

    public const DISPOSITION_STORED = 'stored';

    public const DISPOSITION_DAMAGED = 'damaged';

    public const DISPOSITION_LOST = 'lost';

    /** @var list<string> */
    public const DISPOSITIONS = [
        self::DISPOSITION_RETURNED,
        self::DISPOSITION_STORED,
        self::DISPOSITION_DAMAGED,
        self::DISPOSITION_LOST,
    ];

    protected $fillable = [
        'shipment_id',
        'receiver_id',
        'received_at',
        'quantity',
        'quantities',
        'condition',
        'disposition',
        'ownership',
        'notes',
    ];

    protected $attributes = [
        'disposition' => self::DISPOSITION_STORED,
        'ownership' => 'receiver_office',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $receipt): void {
            if (! $receipt->shipment_id) {
                throw ValidationException::withMessages(['shipment_id' => 'A sample shipment is required.']);
            }
            if (! $receipt->receiver_id) {
                throw ValidationException::withMessages(['receiver_id' => 'A receiving user is required.']);
            }
            if (! is_numeric($receipt->quantity) || bccomp((string) $receipt->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'A positive received quantity is required.']);
            }
            $condition = $receipt->getAttribute('condition');
            $condition = $condition instanceof SampleShipmentCondition ? $condition->value : (string) $condition;
            if (SampleShipmentCondition::tryFrom($condition) === null) {
                throw ValidationException::withMessages(['condition' => 'The receipt condition is invalid.']);
            }
            if (! in_array($receipt->disposition, self::DISPOSITIONS, true)) {
                throw ValidationException::withMessages(['disposition' => 'The receipt disposition is invalid.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'receiver_id' => 'integer',
            'received_at' => 'date',
            'quantity' => 'decimal:2',
            'quantities' => 'array',
            'condition' => SampleShipmentCondition::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(SampleShipment::class, 'shipment_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function conditionValue(): string
    {
        return $this->condition instanceof SampleShipmentCondition ? $this->condition->value : (string) $this->condition;
    }
}
