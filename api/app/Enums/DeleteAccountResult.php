<?php

namespace App\Enums;

enum DeleteAccountResult
{
    case Deleted;
    case MainPersonalAccount;
    case LastPersonalAccount;
    case AttachedToProcess;
}
