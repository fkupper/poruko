<?php

namespace App\Modules\Ledger\Exceptions;

use RuntimeException;

class CannotSettlePeriodWithEarlierOpenPeriodsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Earlier open periods must be settled first.');
    }
}
