<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMcpActionLogRequest;
use App\Http\Resources\McpActionLogResource;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Mcp\Queries\McpActionLogIndexQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class McpActionLogController extends Controller
{
    public function index(
        IndexMcpActionLogRequest $request,
        Ledger $ledger,
        McpActionLogIndexQuery $query,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $perPage = (int) ($request->validated('per_page') ?? 25);
        $page = (int) ($request->validated('page') ?? 1);

        return McpActionLogResource::collection(
            $query->execute($ledger, $user, $perPage, $page),
        );
    }
}
