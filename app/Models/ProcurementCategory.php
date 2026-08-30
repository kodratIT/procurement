<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcurementCategoryType;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementCategoryConfiguration;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementCategory extends Model
{
    use HasFactory;

    public const TYPE_GOODS = ProcurementCategoryType::GOODS->value;

    public const TYPE_SERVICE = ProcurementCategoryType::SERVICE->value;

    public const TYPE_MIXED = ProcurementCategoryType::MIXED->value;

    public const TYPES = [
        self::TYPE_GOODS => 'Barang',
        self::TYPE_SERVICE => 'Jasa',
        self::TYPE_MIXED => 'Barang dan Jasa',
    ];

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'requires_batch',
        'requires_jamaah',
        'requires_vendor',
        'requires_quotation',
        'requires_receipt',
        'requires_invoice',
        'requires_po',
        'workflow_reference',
        'number_template',
        'is_active',
        'disabled_at',
    ];

    protected $attributes = [
        'type' => self::TYPE_GOODS,
        'requires_batch' => false,
        'requires_jamaah' => false,
        'requires_vendor' => false,
        'requires_quotation' => false,
        'requires_receipt' => false,
        'requires_invoice' => false,
        'requires_po' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            ProcurementCategoryType::from((string) ($category->getAttributes()['type'] ?? self::TYPE_GOODS));

            if ($category->is_active) {
                $category->disabled_at = null;
            } elseif ($category->disabled_at === null) {
                $category->disabled_at = now();
            }

            if (Auth::check()) {
                app(MultiOfficeAuthorization::class)->authorizeMutation(
                    Auth::user(),
                    $category,
                    ProcurementPermissions::MANAGE_MASTER_DATA,
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ProcurementCategoryType::class,
            'requires_batch' => 'boolean',
            'requires_jamaah' => 'boolean',
            'requires_vendor' => 'boolean',
            'requires_quotation' => 'boolean',
            'requires_receipt' => 'boolean',
            'requires_invoice' => 'boolean',
            'requires_po' => 'boolean',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'category_id');
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'category_id');
    }

    public function numberTemplate(): HasOne
    {
        return $this->hasOne(DocumentNumberConfiguration::class, 'transaction_type', 'number_template');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeAvailableForNewPurchaseRequests(Builder $query): Builder
    {
        return $query->active();
    }

    public function configuration(): ProcurementCategoryConfiguration
    {
        return new ProcurementCategoryConfiguration(
            type: $this->type instanceof ProcurementCategoryType
                ? $this->type
                : ProcurementCategoryType::from((string) $this->type),
            requiresBatch: (bool) $this->requires_batch,
            requiresJamaah: (bool) $this->requires_jamaah,
            requiresVendor: (bool) $this->requires_vendor,
            requiresQuotation: (bool) $this->requires_quotation,
            requiresReceipt: (bool) $this->requires_receipt,
            requiresInvoice: (bool) $this->requires_invoice,
            requiresPurchaseOrder: (bool) $this->requires_po,
            workflowReference: $this->workflow_reference,
            numberTemplate: $this->number_template,
        );
    }

    public function deactivate(): bool
    {
        return DB::transaction(fn (): bool => $this->forceFill([
            'is_active' => false,
            'disabled_at' => now(),
        ])->save());
    }

    public function activate(): bool
    {
        return DB::transaction(fn (): bool => $this->forceFill([
            'is_active' => true,
            'disabled_at' => null,
        ])->save());
    }
}
