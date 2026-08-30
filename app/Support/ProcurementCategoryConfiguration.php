<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProcurementCategoryType;

final readonly class ProcurementCategoryConfiguration
{
    public function __construct(
        public ProcurementCategoryType $type,
        public bool $requiresBatch,
        public bool $requiresJamaah,
        public bool $requiresVendor,
        public bool $requiresQuotation,
        public bool $requiresReceipt,
        public bool $requiresInvoice,
        public bool $requiresPurchaseOrder,
        public ?string $workflowReference,
        public ?string $numberTemplate,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function flagLabels(): array
    {
        return [
            'requires_batch' => 'Wajib batch keberangkatan',
            'requires_jamaah' => 'Wajib terkait jamaah',
            'requires_vendor' => 'Wajib vendor',
            'requires_quotation' => 'Wajib quotation',
            'requires_receipt' => 'Wajib penerimaan',
            'requires_invoice' => 'Wajib invoice',
            'requires_po' => 'Wajib purchase order',
        ];
    }

    public function requiresBatch(): bool
    {
        return $this->requiresBatch;
    }

    public function requiresJamaah(): bool
    {
        return $this->requiresJamaah;
    }

    public function requiresVendor(): bool
    {
        return $this->requiresVendor;
    }

    public function requiresQuotation(): bool
    {
        return $this->requiresQuotation;
    }

    public function requiresReceipt(): bool
    {
        return $this->requiresReceipt;
    }

    public function requiresInvoice(): bool
    {
        return $this->requiresInvoice;
    }

    public function requiresPurchaseOrder(): bool
    {
        return $this->requiresPurchaseOrder;
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'requires_batch' => $this->requiresBatch,
            'requires_jamaah' => $this->requiresJamaah,
            'requires_vendor' => $this->requiresVendor,
            'requires_quotation' => $this->requiresQuotation,
            'requires_receipt' => $this->requiresReceipt,
            'requires_invoice' => $this->requiresInvoice,
            'requires_po' => $this->requiresPurchaseOrder,
            'workflow_reference' => $this->workflowReference,
            'number_template' => $this->numberTemplate,
        ];
    }
}
