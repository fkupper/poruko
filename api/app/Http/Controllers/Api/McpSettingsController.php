<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMcpSettingsRequest;
use App\Http\Resources\McpSettingsResource;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class McpSettingsController extends Controller
{
    public function show(Ledger $ledger): JsonResponse
    {
        return McpSettingsResource::make($this->membership($ledger))
            ->response();
    }

    public function update(UpdateMcpSettingsRequest $request, Ledger $ledger): JsonResponse
    {
        $membership = $this->membership($ledger);
        $validated = $request->validated();

        $membership->update([
            'mcp_enabled' => (bool) $validated['enabled'],
            'mcp_allow_read' => (bool) $validated['allow_read'],
            'mcp_allow_write' => (bool) $validated['allow_write'],
            'mcp_allow_destructive' => (bool) $validated['allow_destructive'],
            'mcp_post_mode' => $validated['post_mode'],
        ]);

        return McpSettingsResource::make($membership->refresh())->response();
    }

    private function membership(Ledger $ledger): LedgerUser
    {
        /** @var User $user */
        $user = request()->user();

        $membership = LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership instanceof LedgerUser) {
            throw new NotFoundHttpException('Ledger membership not found.');
        }

        return $membership;
    }
}
