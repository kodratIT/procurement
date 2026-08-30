<?php

namespace App\Models;

use App\Models\Concerns\OfficeScoped;
use App\Services\PurchaseRequestNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseRequest extends Model
{
    use HasFactory, OfficeScoped;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'pr_number',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'departure_batch_id',
        'requester_id',
        'title',
        'notes',
        'required_date',
        'status',
        'total_amount',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->status === null) {
                $model->status = self::STATUS_DRAFT;
            }

            // Header totals are derived exclusively from persisted item lines.
            // Never retain a client-provided value during initial creation.
            $model->total_amount = 0;

            // Assign the server-side sequential number at first persist.
            // A client-supplied number is never accepted.
            $model->pr_number = app(PurchaseRequestNumberService::class)->next();

            if ($model->office_id === null) {
                throw new \LogicException('purchase_requests.office_id is required (office scoping).');
            }
        });
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'cost_center_id' => 'integer',
            'departure_batch_id' => 'integer',
            'requester_id' => 'integer',
            'required_date' => 'date',
            'total_amount' => 'decimal:2',
            'status' => 'string',
        ];
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

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function departureBatch(): BelongsTo
    {
        return $this->belongsTo(DepartureBatch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    /**
     * Server-side total: sum of the item line totals.
     * Never trust client-sent totals.
     */
    public function recalculateTotal(): void
    {
        $this->forceFill(['total_amount' => (string) $this->items()->sum('line_total')])->saveQuietly();
    }

    /**
     * Recalculate line totals and the header total inside a transaction.
     */
    public function syncTotals(): void
    {
        DB::transaction(function (): void {
            $this->items->each(function (PurchaseRequestItem $item): void {
                $item->calculateLineTotal();
                $item->save();
            });

            $this->recalculateTotal();
            $this->save();
        });
    }
}
