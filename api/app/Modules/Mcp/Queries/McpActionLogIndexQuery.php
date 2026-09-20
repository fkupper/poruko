<?php

namespace App\Modules\Mcp\Queries;

use App\Models\Ledger;
use App\Models\McpActionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class McpActionLogIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, McpActionLog>
     */
    public function execute(Ledger $ledger, User $viewer, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $query = McpActionLog::query()
            ->where('ledger_id', $ledger->id)
            ->with(['user'])
            ->latest('created_at')
            ->latest('id');

        if (!$viewer->can('settings')) {
            $query->where('user_id', $viewer->id);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
