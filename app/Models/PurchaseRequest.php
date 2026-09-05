<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\OfficeScoped;
use App\Services\PurchaseRequestTotalCalculator;
use App\Services\WorkflowStageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequest extends Model
{
    use HasFactory, OfficeScoped;

    public const STATUS_DRAFT = PurchaseRequestStatus::Draft->value;

    public const STATUS_SUBMITTED = PurchaseRequestStatus::Submitted->value;

    public const STATUS_PROCUREMENT_REVIEW = PurchaseRequestStatus::ProcurementReview->value;

    public const STATUS_PENDING_APPROVAL = PurchaseRequestStatus::PendingApproval->value;

    public const STATUS_APPROVED = PurchaseRequestStatus::Approved->value;

    public const STATUS_REJECTED = PurchaseRequestStatus::Rejected->value;

    public const STATUS_RETURNED = PurchaseRequestStatus::Returned->value;

    public const STATUS_COMPLETED = PurchaseRequestStatus::Completed->value;

    public const STATUS_CANCELLED = PurchaseRequestStatus::Cancelled->value;

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_PROCUREMENT_REVIEW,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** @var list<string> */
    public const DRAFT_PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'pr_number',
        'office_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'umrah_batch_id',
        'requester_id',
        'category_id',
        'vendor_id',
        'title',
        'notes',
        'reason',
        'required_date',
        'priority',
        'status',
        'total_amount',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->status ??= self::STATUS_DRAFT;
            $rawStatus = (string) $model->getAttribute('status');
            $status = PurchaseRequestStatus::tryFrom($rawStatus);

            if ($status === null) {
                // Allow dynamic workflow stage keys (step_key) for flexible workflows
                $isDynamic = false;
                try {
                    $isDynamic = app(WorkflowStageService::class)->isDynamicStage($rawStatus, $model);
                } catch (\Throwable) {
                    $isDynamic = (bool) preg_match('/^[a-z0-9_]+$/', $rawStatus);
                }

                if (! $isDynamic) {
                    throw ValidationException::withMessages([
                        'status' => 'The purchase request status is invalid.',
                    ]);
                }
            }

            if ($model->exists && $model->isDirty('pr_number')) {
                throw new \LogicException('Purchase request numbers are assigned by the lifecycle service.');
            }
        });

        static::creating(function (self $model): void {
            if ($model->category_id !== null && ! self::categoryIsAvailableForNewRequest((int) $model->category_id)) {
                throw ValidationException::withMessages([
                    'category_id' => 'The selected procurement category is inactive and cannot be used for a new purchase request.',
                ]);
            }

            $model->status ??= self::STATUS_DRAFT;
            $model->total_amount = '0.00';
            $model->pr_number = null;

            if ($model->office_id === null) {
                throw new \LogicException('purchase_requests.office_id is required (office scoping).');
            }
        });

        static::created(function (self $model): void {
            $model->forceFill(['pr_number' => 'DRAFT-'.$model->getKey()])->saveQuietly();
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
            'umrah_batch_id' => 'integer',
            'requester_id' => 'integer',
            'vendor_id' => 'integer',
            'recommended_quotation_id' => 'integer',
            'recommendation_version' => 'integer',
            'recommended_by_id' => 'integer',
            'required_date' => 'date',
            'recommended_at' => 'datetime',
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

    public function umrahBatch(): BelongsTo
    {
        return $this->belongsTo(UmrahBatch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcurementCategory::class, 'category_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function recommendedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'recommended_quotation_id');
    }

    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by_id');
    }

    public function quotationRecommendations(): HasMany
    {
        return $this->hasMany(QuotationRecommendation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchaseRequestStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<ApprovalInstance> */
    public function approvalInstances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class);
    }

    public function isCorrectable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(PurchaseRequestFieldValue::class, 'purchase_request_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Recalculate the header from persisted item lines.
     */
    public function recalculateTotal(): void
    {
        app(PurchaseRequestTotalCalculator::class)->recalculateHeader($this);
    }

    /**
     * Recalculate all persisted item and header totals atomically.
     */
    public function syncTotals(): void
    {
        app(PurchaseRequestTotalCalculator::class)->sync($this);
    }

    private static function categoryIsAvailableForNewRequest(int $categoryId): bool
    {
        return ProcurementCategory::query()
            ->availableForNewPurchaseRequests()
            ->whereKey($categoryId)
            ->exists();
    }
}
