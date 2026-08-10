<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        // Global roles (team_id null) plus any roles scoped to this ledger.
        $roles = Role::with('permissions')
            ->where(function ($query) use ($ledger): void {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $ledger->id);
            })
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
