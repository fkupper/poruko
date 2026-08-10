<?php

namespace App\Enums;

enum AccountType: string
{
    case PoolAsset = 'pool_asset';
    case SpaceExpense = 'space_expense';
    case SplitClearing = 'split_clearing';
    case UserFunding = 'user_funding';
    case UserLiability = 'user_liability';

    /**
     * @return array<int, string>
     */
    public static function internalTypes(): array
    {
        return [
            self::SplitClearing->value,
            self::UserLiability->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function publicTypes(): array
    {
        return [
            self::PoolAsset->value,
            self::SpaceExpense->value,
            self::UserFunding->value,
        ];
    }
}
