<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleShipmentCondition;
use App\Enums\SampleShipmentStatus;
use App\Models\Concerns\OfficeScoped;
use Database\Factories\SampleShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

final class SampleShipment extends Model
{
    /** @use HasFactory<SampleShipmentFactory> */
    use HasFactory, OfficeScoped;

    public const STATUS_DRAFT = SampleShipmentStatus::Draft->value;

    public const STATUS_SUBMITTED = SampleShipmentStatus::Submitted->value;

    public const STATUS_PROCUREMENT_REVIEW = SampleShipmentStatus::ProcurementReview->value;

    public const STATUS_APPROVED = SampleShipmentStatus::Approved->value;

    public const STATUS_SHIPPED = SampleShipmentStatus::Shipped->value;

    public const STATUS_RECEIVED = SampleShipmentStatus::Received->value;

    public const STATUS_CONFIRMED = SampleShipmentStatus::Confirmed->value;

    public const STATUS_RETURNED = SampleShipmentStatus::Returned->value;

    public const STATUS_STORED = SampleShipmentStatus::Stored->value;

    public const STATUS_COMPLETE = SampleShipmentStatus::Complete->value;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_PROCUREMENT_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_SHIPPED,
        self::STATUS_RECEIVED,
        self::STATUS_CONFIRMED,
        self::STATUS_RETURNED,
        self::STATUS_STORED,
        self::STATUS_COMPLETE,
    ];

    public const APPROVAL_ROUTE_PROCUREMENT = 'procurement';

    public const APPROVAL_ROUTE_FINANCE = 'finance';

    /** @var list<string> */
    public const APPROVAL_ROUTES = [self::APPROVAL_ROUTE_PROCUREMENT, self::APPROVAL_ROUTE_FINANCE];

    /** @var list<string> */
    public const OWNERSHIPS = ['sender_office', 'receiver_office', 'company', 'returned', 'stored', 'damaged', 'lost'];

    protected $fillable = [
        'shipment_number',
        'purchase_order_id',
        'office_id',
        'sender_office_id',
        'receiver_office_id',
        'sender_id',
        'receiver_id',
        'cost_center_id',
        'purpose',
        'requested_at',
        'planned_ship_date',
        'shipped_at',
        'received_at',
        'confirmed_at',
        'returned_at',
        'completed_at',
        'tracking_no',
        'shipping_cost',
        'currency',
        'approval_route',
        'condition',
        'ownership',
        'status',
        'notes',
    ];

    protected $attributes = [
        'shipping_cost' => '0.00',
        'currency' => 'IDR',
        'approval_route' => self::APPROVAL_ROUTE_PROCUREMENT,
        'condition' => SampleShipmentCondition::Good->value,
        'ownership' => 'sender_office',
        'status' => self::STATUS_DRAFT,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $shipment): void {
            $status = $shipment->getAttribute('status');
            $status = $status instanceof SampleShipmentStatus ? $status->value : (string) $status;
            if (SampleShipmentStatus::tryFrom($status) === null) {
                throw ValidationException::withMessages(['status' => 'The sample shipment status is invalid.']);
            }

            $condition = $shipment->getAttribute('condition');
            $condition = $condition instanceof SampleShipmentCondition ? $condition->value : (string) $condition;
            if (SampleShipmentCondition::tryFrom($condition) === null) {
                throw ValidationException::withMessages(['condition' => 'The sample shipment condition is invalid.']);
            }

            if (! in_array($shipment->approval_route, self::APPROVAL_ROUTES, true)) {
                throw ValidationException::withMessages(['approval_route' => 'The sample shipment approval route is invalid.']);
            }
            if (! in_array($shipment->ownership, self::OWNERSHIPS, true)) {
                throw ValidationException::withMessages(['ownership' => 'The sample shipment ownership is invalid.']);
            }
            if ($shipment->sender_office_id !== null && $shipment->office_id !== null
                && (int) $shipment->sender_office_id !== (int) $shipment->office_id) {
                throw ValidationException::withMessages(['sender_office_id' => 'The sender office must match the shipment office scope.']);
            }

            if ($shipment->exists && $shipment->isDirty('shipment_number')
                && $shipment->getOriginal('shipment_number') !== null) {
                throw new \LogicException('Sample shipment numbers are immutable.');
            }
        });

        self::creating(function (self $shipment): void {
            if ($shipment->office_id === null && $shipment->sender_office_id !== null) {
                $shipment->office_id = $shipment->sender_office_id;
            }
            if ($shipment->sender_office_id === null && $shipment->office_id !== null) {
                $shipment->sender_office_id = $shipment->office_id;
            }
            $shipment->requested_at ??= now()->toDateString();
        });

        self::created(function (self $shipment): void {
            if ($shipment->shipment_number === null || $shipment->shipment_number === '') {
                $shipment->forceFill(['shipment_number' => 'DRAFT-'.$shipment->getKey()])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'office_id' => 'integer',
            'sender_office_id' => 'integer',
            'receiver_office_id' => 'integer',
            'sender_id' => 'integer',
            'receiver_id' => 'integer',
            'cost_center_id' => 'integer',
            'requested_at' => 'date',
            'planned_ship_date' => 'date',
            'shipped_at' => 'date',
            'received_at' => 'date',
            'confirmed_at' => 'date',
            'returned_at' => 'date',
            'completed_at' => 'date',
            'shipping_cost' => 'decimal:2',
            'status' => SampleShipmentStatus::class,
            'condition' => SampleShipmentCondition::class,
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function senderOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'sender_office_id');
    }

    public function receiverOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'receiver_office_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SampleShipmentItem::class, 'shipment_id')->orderBy('id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(SampleShipmentReceipt::class, 'shipment_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function statusValue(): string
    {
        return $this->status instanceof SampleShipmentStatus ? $this->status->value : (string) $this->status;
    }

    public function conditionValue(): string
    {
        return $this->condition instanceof SampleShipmentCondition ? $this->condition->value : (string) $this->condition;
    }

    public function isTerminal(): bool
    {
        return $this->statusValue() === self::STATUS_COMPLETE;
    }

    /** @return Builder<self> */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_COMPLETE);
    }
}
