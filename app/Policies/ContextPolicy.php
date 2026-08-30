<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\MultiOfficeAuthorization;
use App\Support\ProcurementPermissions;
use Illuminate\Database\Eloquent\Model;

final class ContextPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->canView($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->authorization->canView($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->authorization->canCreate($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->authorization->canUpdate($user, $record, true);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->authorization->canDelete($user, $record, true);
    }

    public function deleteAny(User $user): bool
    {
        return $this->authorization->can($user, ProcurementPermissions::DELETE);
    }

    public function deactivate(User $user, Model $record): bool
    {
        return $this->authorization->canUpdate($user, $record, true)
            && (bool) $record->getAttribute('is_active');
    }
}
