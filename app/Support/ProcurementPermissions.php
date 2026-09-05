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

    public const RECEIVE = 'procurement.receive';

    public const CORRECT_RECEIPT = 'procurement.correct-receipt';

    public const MANAGE_USERS = 'procurement.manage-users';

    public const MANAGE_ROLES = 'procurement.manage-roles';

    public const MANAGE_FEATURES = 'procurement.manage-features';

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
            self::RECEIVE,
            self::CORRECT_RECEIPT,
            self::MANAGE_USERS,
            self::MANAGE_ROLES,
            self::MANAGE_FEATURES,
        ];
    }
}
