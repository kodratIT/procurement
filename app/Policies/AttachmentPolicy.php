<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PilgrimDistributionItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;

final class AttachmentPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function view(User $user, Attachment $attachment): bool
    {
        $attachment->loadMissing('attachable');
        $subject = $attachment->attachable;
        if ($subject instanceof Invoice) {
            return $this->authorization->allows($user, ProcurementPermissions::VIEW, $subject);
        }
        if ($subject instanceof Payment) {
            $subject->loadMissing('invoice');

            return $subject->invoice instanceof Invoice
                && $this->authorization->allows($user, ProcurementPermissions::VIEW, $subject->invoice);
        }
        if ($subject instanceof PurchaseOrder) {
            return $this->authorization->allows($user, ProcurementPermissions::VIEW, $subject);
        }
        if ($subject instanceof PilgrimDistributionItem) {
            $subject->loadMissing('distributionItem.distribution.batch');

            return $subject->distributionItem?->distribution?->batch !== null
                && $this->authorization->allows(
                    $user,
                    ProcurementPermissions::VIEW,
                    $subject->distributionItem->distribution->batch,
                );
        }

        return false;
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
