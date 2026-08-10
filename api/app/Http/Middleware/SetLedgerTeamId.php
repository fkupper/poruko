<?php

namespace App\Http\Middleware;

use App\Models\Ledger;
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
            $ledgerId = $ledger instanceof Ledger
                ? $ledger->id
                : (int) $ledger;
            setPermissionsTeamId($ledgerId);
        }

        return $next($request);
    }
}
