<?php

namespace App\Modules\Ledger\Exceptions;

use Exception;

final class CannotUpdateClosedRecurringTransactionException extends Exception
{
    public function __construct()
    {
        parent::__construct('Cannot update a closed recurring transaction. Create a new one instead.');
    }
}
