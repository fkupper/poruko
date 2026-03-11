<?php

namespace App\Enums;

enum TransactionType: string
{
    case Manual = 'manual';
    case Recurring = 'recurring';
    case Settlement = 'settlement';
    case Reversal = 'reversal';
}
