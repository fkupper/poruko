<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFinancialProfileRequest;
use App\Http\Resources\FinancialProfileResource;
use App\Models\FinancialProfile;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Services\FinancialProfileService;
use Illuminate\Http\JsonResponse;

class FinancialProfileController extends Controller
{
    public function update(
        UpdateFinancialProfileRequest $request,
        Ledger $ledger,
        User $user,
        FinancialProfileService $service
    ): JsonResponse {
        $profile = $service->upsertActive(
            $ledger,
            $user,
            $request->validated('incomes'),
            $request->validated('deductions'),
        );

        return FinancialProfileResource::make($profile)
            ->response()
            ->setStatusCode(200);
    }

    public function show(
        Ledger $ledger,
        User $user,
        FinancialProfileService $service
    ): FinancialProfileResource {
        $this->authorize('view', [FinancialProfile::class, $ledger, $user]);

        $profile = $service->activeProfile($ledger, $user, now()->format('Y-m-d'));

        abort_if($profile === null, 404, 'No active financial profile found.');

        return FinancialProfileResource::make($profile);
    }
}
