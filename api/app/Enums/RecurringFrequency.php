<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Annually = 'annually';
}
