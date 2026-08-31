<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class GoodsReceiptPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::VIEW);
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::VIEW, $receipt);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, ProcurementPermissions::RECEIVE);
    }

    public function update(User $user, GoodsReceipt $receipt): bool
    {
        return false;
    }

    public function delete(User $user, GoodsReceipt $receipt): bool
    {
        return false;
    }

    public function correct(User $user, GoodsReceipt $receipt): bool
    {
        $receipt->loadMissing('purchaseOrder');

        return $receipt->purchaseOrder instanceof PurchaseOrder
            && $this->authorization->allows($user, ProcurementPermissions::CORRECT_RECEIPT, $receipt->purchaseOrder);
    }
}
