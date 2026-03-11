<?php

namespace App\Enums;

enum TransactionSplitRule: string
{
    case Equal = 'equal';
    case Individual = 'individual';
}
