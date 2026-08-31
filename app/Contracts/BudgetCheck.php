<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\PurchaseRequest;

interface BudgetCheck
{
    public function check(PurchaseRequest $purchaseRequest): bool;
}
