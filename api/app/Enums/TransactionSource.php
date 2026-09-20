<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Manual = 'manual';
    case Blueprint = 'blueprint';
    case Mcp = 'mcp';
    case AiImport = 'ai_import';
    case System = 'system';
}
