<?php

namespace App\Enums;

enum PostingDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
