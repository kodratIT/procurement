<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;
use LogicException;

final class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    public const STATUS_NOT_RECEIVED = 'not_received';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_COMPLETE = 'complete';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_NOT_RECEIVED,
        self::STATUS_PARTIALLY_RECEIVED,
        self::STATUS_COMPLETE,
    ];

    protected $fillable = [
        'purchase_order_id',
        'received_date',
        'receiver_id',
        'office_id',
        'branch_id',
        'department_id',
        'status',
        'correction_of_id',
        'correction_reason',
        'notes',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $receipt): void {
            if (! in_array($receipt->status, self::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'The goods receipt status is invalid.']);
            }
            if ($receipt->correction_of_id !== null && blank($receipt->correction_reason)) {
                throw ValidationException::withMessages(['correction_reason' => 'A correction reason is required.']);
            }
            if ($receipt->exists) {
                throw new LogicException('Goods receipts are immutable; use the correction service.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Goods receipt history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'received_date' => 'date',
            'receiver_id' => 'integer',
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'correction_of_id' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isCorrection(): bool
    {
        return $this->correction_of_id !== null;
    }
}
