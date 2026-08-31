<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuotationRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class QuotationRecommendation extends Model
{
    /** @use HasFactory<QuotationRecommendationFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'quotation_id',
        'vendor_id',
        'recommended_by_id',
        'office_id',
        'version',
        'reason',
        'evidence_attachment_ids',
        'comparison_snapshot',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Quotation recommendation history is immutable.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Quotation recommendation history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'quotation_id' => 'integer',
            'vendor_id' => 'integer',
            'recommended_by_id' => 'integer',
            'office_id' => 'integer',
            'version' => 'integer',
            'evidence_attachment_ids' => 'array',
            'comparison_snapshot' => 'array',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
