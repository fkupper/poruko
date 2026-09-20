<?php

namespace App\Http\Controllers\Api;

use App\Enums\McpOperation;
use App\Http\Controllers\Controller;
use App\Mcp\Support\A2UiSurface;
use App\Models\Ledger;
use App\Models\PendingTransaction;
use App\Models\User;
use App\Modules\Ledger\Queries\PendingTransactionIndexQuery;
use App\Modules\Mcp\Actions\LogMcpActionAction;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;
use App\Modules\Mcp\Services\McpCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpA2uiController extends Controller
{
    public function __invoke(
        Request $request,
        Ledger $ledger,
        PendingTransactionIndexQuery $pendingTransactions,
        McpCapabilityService $capabilities,
        LogMcpActionAction $logAction,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $startedAt = microtime(true);
        $status = 'error';

        try {
            $capabilities->assert($user, $ledger, McpOperation::Read);
            $this->authorize('viewAny', [PendingTransaction::class, $ledger]);
            $surface = A2UiSurface::pendingApprovals($pendingTransactions->execute($ledger));
            $status = 'ok';

            return response()->json(['data' => $surface]);
        } catch (McpAuthorizationException $exception) {
            $status = 'denied';

            abort(403, $exception->getMessage());
        } finally {
            $logAction->execute(
                $user,
                $ledger->id,
                'render-pending-approvals-ui',
                McpOperation::Read,
                [],
                $status,
                (int) round((microtime(true) - $startedAt) * 1000),
            );
        }
    }
}
