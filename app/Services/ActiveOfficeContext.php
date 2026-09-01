<?php

namespace App\Services;

use App\Models\Office;
use App\Models\UserAssignment;

/**
 * @deprecated Use AccessContextService for office, branch, department, and role context.
 */
class ActiveOfficeContext extends AccessContextService
{
    public const SESSION_KEY = AccessContextService::LEGACY_SESSION_KEY;

    public function set(Office|int $office): Office
    {
        return parent::set($office);
    }

    public function setContext(array|UserAssignment $context): UserAssignment
    {
        return parent::setContext($context);
    }
}
