<?php

declare(strict_types=1);

namespace App\Support;

final class ProcurementPermissions
{
    public const VIEW = 'procurement.view';

    public const CREATE = 'procurement.create';

    public const UPDATE = 'procurement.update';

    public const DELETE = 'procurement.delete';

    public const SUBMIT = 'procurement.submit';

    public const APPROVE = 'procurement.approve';

    public const EXPORT = 'procurement.export';

    public const MANAGE_MASTER_DATA = 'procurement.manage-master-data';

    public const MANAGE_FINANCE = 'procurement.manage-finance';

    public const MANAGE_USERS = 'procurement.manage-users';

    public const MANAGE_ROLES = 'procurement.manage-roles';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::SUBMIT,
            self::APPROVE,
            self::EXPORT,
            self::MANAGE_MASTER_DATA,
            self::MANAGE_FINANCE,
            self::MANAGE_USERS,
            self::MANAGE_ROLES,
        ];
    }
}
