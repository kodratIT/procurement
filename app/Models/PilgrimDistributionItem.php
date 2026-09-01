<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PilgrimDistributionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

final class PilgrimDistributionItem extends Model
{
    /** @use HasFactory<PilgrimDistributionItemFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RECEIVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'distribution_item_id',
        'pilgrim_id',
        'quantity',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $allocation): void {
            if (! is_numeric($allocation->quantity) || bccomp((string) $allocation->quantity, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Pilgrim allocation quantities must be greater than zero.']);
            }

            if (! in_array((string) $allocation->status, self::STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'The pilgrim distribution status is invalid.']);
            }

            $distributionItem = DistributionItem::query()
                ->with('distribution')
                ->find($allocation->distribution_item_id);
            $pilgrim = Pilgrim::query()
                ->withoutGlobalScopes()
                ->find($allocation->pilgrim_id);

            if (! $distributionItem instanceof DistributionItem || ! $distributionItem->distribution instanceof Distribution) {
                throw ValidationException::withMessages(['distribution_item_id' => 'A valid distribution item is required.']);
            }

            if (! $pilgrim instanceof Pilgrim) {
                throw ValidationException::withMessages(['pilgrim_id' => 'A valid pilgrim is required.']);
            }

            if (! $distributionItem->distribution->isIndividualMode()) {
                throw ValidationException::withMessages(['distribution' => 'Pilgrim allocations require individual receipt mode.']);
            }

            if ((int) $pilgrim->umrah_batch_id !== (int) $distributionItem->distribution->umrah_batch_id) {
                throw ValidationException::withMessages(['pilgrim_id' => 'The pilgrim must belong to the distribution batch.']);
            }

            $allocated = self::query()
                ->where('distribution_item_id', $distributionItem->getKey())
                ->when($allocation->exists, fn ($query) => $query->where('id', '!=', $allocation->getKey()))
                ->sum('quantity');
            if (bccomp(bcadd((string) $allocated, (string) $allocation->quantity, 2), (string) $distributionItem->quantity, 2) > 0) {
                throw ValidationException::withMessages(['quantity' => 'Pilgrim allocations cannot exceed the distribution quantity.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'distribution_item_id' => 'integer',
            'pilgrim_id' => 'integer',
            'quantity' => 'decimal:2',
        ];
    }

    public function distributionItem(): BelongsTo
    {
        return $this->belongsTo(DistributionItem::class);
    }

    public function pilgrim(): BelongsTo
    {
        return $this->belongsTo(Pilgrim::class);
    }

    public function countsTowardsReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
