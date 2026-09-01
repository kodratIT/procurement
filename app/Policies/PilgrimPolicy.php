<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pilgrim;
use App\Models\User;
use App\Services\MultiOfficeAuthorization;

final class PilgrimPolicy
{
    public function __construct(private readonly MultiOfficeAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->canView($user);
    }

    public function view(User $user, Pilgrim $pilgrim): bool
    {
        return $this->authorization->canView($user, $pilgrim);
    }

    public function create(User $user): bool
    {
        return $this->authorization->canCreate($user);
    }

    public function update(User $user, Pilgrim $pilgrim): bool
    {
        return $this->authorization->canUpdate($user, $pilgrim, true);
    }

    public function delete(User $user, Pilgrim $pilgrim): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user, Pilgrim $pilgrim): bool
    {
        return $pilgrim->is_active && $this->authorization->canUpdate($user, $pilgrim, true);
    }

    public function activate(User $user, Pilgrim $pilgrim): bool
    {
        return ! $pilgrim->is_active && $this->authorization->canUpdate($user, $pilgrim, true);
    }
}
