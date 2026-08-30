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
