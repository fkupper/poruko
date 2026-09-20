<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\EnforceTwoFactor;
use App\Mcp\Servers\PorukoLedgerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/poruko', PorukoLedgerServer::class)
    ->middleware([AuthenticateMcp::class, EnforceTwoFactor::class])
    ->name('mcp.poruko');
