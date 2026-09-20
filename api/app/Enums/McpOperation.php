<?php

namespace App\Enums;

enum McpOperation: string
{
    case Read = 'read';
    case Write = 'write';
    case Destructive = 'destructive';
}
