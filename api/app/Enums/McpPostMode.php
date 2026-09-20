<?php

namespace App\Enums;

enum McpPostMode: string
{
    case Direct = 'direct';
    case ApprovalQueue = 'approval_queue';
}
