<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleShipmentCondition;
use Database\Factories\SampleShipmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

final class SampleShipmentItem extends Model
{
    /** @use HasFactory<SampleShipmentItemFactory> */
    use HasFactory;

    /** @var list<string> */
    public const OWNERSHIPS = SampleShipment::OWNERSHIPS;

    protected $fillable = [
        'shipment_id',
        'purchase_order_item_id',
        'procurement_item_id',
        'procurement_variant_id',
        'quantity',
        'condition',
        'ownership',
        'notes',
    ];

    protected $attributes = [
        'condition' => SampleShipmentCondition::Good->value,
        'ownership' => 'sender_office',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $item): void {
            if (! $item->shipment_id) {
                throw ValidationException::withMessages(['shipment_id' => 'A sample shipment is required.']);
            }
            if (! $item->procurement_item_id) {
                throw ValidationException::withMessages(['procurement_item_id' => 'A procurement item is required.']);
            }
            if (! is_numeric($item->quantity) || bccomp((string) $item->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Sample shipment quantities must be greater than zero.']);
            }
            $condition = $item->getAttribute('condition');
            $condition = $condition instanceof SampleShipmentCondition ? $condition->value : (string) $condition;
            if (SampleShipmentCondition::tryFrom($condition) === null) {
                throw ValidationException::withMessages(['condition' => 'The sample shipment item condition is invalid.']);
            }
            if (! in_array($item->ownership, self::OWNERSHIPS, true)) {
                throw ValidationException::withMessages(['ownership' => 'The sample shipment item ownership is invalid.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'purchase_order_item_id' => 'integer',
            'procurement_item_id' => 'integer',
            'procurement_variant_id' => 'integer',
            'quantity' => 'decimal:2',
            'condition' => SampleShipmentCondition::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(SampleShipment::class, 'shipment_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function procurementVariant(): BelongsTo
    {
        return $this->belongsTo(ProcurementVariant::class);
    }

    public function conditionValue(): string
    {
        return $this->condition instanceof SampleShipmentCondition ? $this->condition->value : (string) $this->condition;
    }
}
