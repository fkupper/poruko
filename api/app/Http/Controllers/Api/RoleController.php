<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request, \App\Models\Ledger $ledger): \Illuminate\Http\JsonResponse
    {
        // For now, return global roles and their permissions.
        // If we add custom roles per space in the future, we would filter by team_id = $ledger->id.
        $roles = \Spatie\Permission\Models\Role::with('permissions')
            ->whereNull('team_id')
            ->orWhere('team_id', $ledger->id)
            ->get();

        return response()->json(
            $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]),
        );
    }
}
