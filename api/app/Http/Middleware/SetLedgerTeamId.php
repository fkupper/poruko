<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLedgerTeamId
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($ledger = $request->route('ledger')) {
            $ledgerId = $ledger instanceof \App\Models\Ledger ? $ledger->id : $ledger;
            setPermissionsTeamId($ledgerId);
        }

        return $next($request);
    }
}
