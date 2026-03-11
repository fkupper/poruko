<?php

namespace App\Enums;

enum AccountType: string
{
    case Personal = 'personal';
    case Pool = 'pool';
    case External = 'external';
}
