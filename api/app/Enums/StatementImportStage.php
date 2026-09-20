<?php

namespace App\Enums;

enum StatementImportStage: string
{
    case Queued = 'queued';
    case Parsing = 'parsing';
    case MappingAccounts = 'mapping_accounts';
    case ProcessingTransactions = 'processing_transactions';
    case Completed = 'completed';
    case Failed = 'failed';
}
