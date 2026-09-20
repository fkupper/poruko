<?php

namespace App\Http\Controllers\Api;

use App\Enums\AiProvider;
use App\Enums\PendingTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAiImportSettingsRequest;
use App\Http\Requests\UpdateBankAccountMappingRequest;
use App\Http\Requests\UploadBankStatementRequest;
use App\Http\Resources\BankAccountMappingResource;
use App\Http\Resources\StatementImportResource;
use App\Jobs\ProcessBankStatementImportJob;
use App\Models\AiProviderSetting;
use App\Models\BankAccountMapping;
use App\Models\Ledger;
use App\Models\LedgerUser;
use App\Models\PendingTransaction;
use App\Models\StatementImport;
use App\Models\User;
use App\Modules\Ledger\Services\OpenAiCompatibleEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class AiStatementImportController extends Controller
{
    public function settings(Request $request, Ledger $ledger): JsonResponse
    {
        $user = $this->authorizedUser($request, $ledger);
        $setting = AiProviderSetting::query()->where('user_id', $user->id)->first();
        $membership = $this->membership($ledger, $user);

        return response()->json([
            'data' => [
                'configured' => $setting instanceof AiProviderSetting,
                'provider' => $setting?->provider,
                'base_url' => $setting?->base_url,
                'model' => $setting?->model,
                'masked_api_key' => $setting === null ? null : '••••••••' . $setting->api_key_last_four,
                'auto_create_accounts' => $membership->ai_import_auto_create_accounts,
            ],
        ]);
    }

    public function saveSettings(
        SaveAiImportSettingsRequest $request,
        Ledger $ledger,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $existing = AiProviderSetting::query()->where('user_id', $user->id)->first();
        $provider = AiProvider::from($validated['provider']);
        $apiKey = $validated['api_key'] ?? null;
        $baseUrl = $provider === AiProvider::OpenAiCompatible
            ? OpenAiCompatibleEndpoint::normalizeForStorage($validated['base_url'])
            : null;

        if (
            $existing instanceof AiProviderSetting
            && $this->requiresNewApiKey($existing, $provider, $baseUrl)
            && (!is_string($apiKey) || $apiKey === '')
        ) {
            return response()->json([
                'message' => 'Enter an API key when changing AI providers or endpoints.',
                'errors' => ['api_key' => ['Enter an API key when changing AI providers or endpoints.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $values = [
            'provider' => $provider,
            'base_url' => $baseUrl,
            'model' => $validated['model'] ?? null,
        ];

        if (is_string($apiKey) && $apiKey !== '') {
            $values['api_key'] = $apiKey;
            $values['api_key_last_four'] = mb_substr($apiKey, -4);
        }

        AiProviderSetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            $values,
        );

        $this->membership($ledger, $user)->update([
            'ai_import_auto_create_accounts' => (bool) $validated['auto_create_accounts'],
        ]);

        return $this->settings($request, $ledger);
    }

    public function destroySettings(Request $request, Ledger $ledger): JsonResponse
    {
        $user = $this->authorizedUser($request, $ledger);

        AiProviderSetting::query()->where('user_id', $user->id)->delete();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    public function mappings(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $user = $this->authorizedUser($request, $ledger);
        $mappings = BankAccountMapping::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->with(['account', 'suggestedAccount'])
            ->latest()
            ->get();

        return BankAccountMappingResource::collection($mappings);
    }

    public function updateMapping(
        UpdateBankAccountMappingRequest $request,
        Ledger $ledger,
        BankAccountMapping $bankAccountMapping,
    ): BankAccountMappingResource {
        $bankAccountMapping->update([
            'account_id' => $request->validated('account_id'),
            'suggested_account_id' => null,
        ]);

        PendingTransaction::query()
            ->where('ledger_id', $ledger->id)
            ->where('status', PendingTransactionStatus::Pending)
            ->where('source', 'ai_import')
            ->where('raw_data->bank_account_mapping_id', $bankAccountMapping->id)
            ->each(function (PendingTransaction $pendingTransaction) use ($bankAccountMapping): void {
                $pendingTransaction->update([
                    'payer_account_id' => $bankAccountMapping->account_id,
                ]);
            });

        return BankAccountMappingResource::make(
            $bankAccountMapping->fresh(['account', 'suggestedAccount']) ?? $bankAccountMapping,
        );
    }

    public function upload(
        UploadBankStatementRequest $request,
        Ledger $ledger,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!AiProviderSetting::query()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Configure your AI provider before uploading a statement.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->file('statement');
        $publicId = (string) Str::uuid();
        $filename = $publicId . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("statement-imports/{$user->id}", $filename, 'local');

        $import = StatementImport::query()->create([
            'public_id' => $publicId,
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'text/plain',
            'file_size' => $file->getSize(),
        ]);

        ProcessBankStatementImportJob::dispatch($import->id);

        return StatementImportResource::make($import)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function imports(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $user = $this->authorizedUser($request, $ledger);
        $imports = StatementImport::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return StatementImportResource::collection($imports);
    }

    private function authorizedUser(Request $request, Ledger $ledger): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->can('view', $ledger) && $user->can('ai_ingestion'),
            Response::HTTP_FORBIDDEN,
        );

        return $user;
    }

    private function membership(Ledger $ledger, User $user): LedgerUser
    {
        return LedgerUser::query()
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    private function requiresNewApiKey(
        AiProviderSetting $existing,
        AiProvider $provider,
        ?string $baseUrl,
    ): bool {
        if ($existing->provider !== $provider) {
            return true;
        }

        if ($provider !== AiProvider::OpenAiCompatible) {
            return false;
        }

        if ($existing->base_url === null || $baseUrl === null) {
            return true;
        }

        try {
            return OpenAiCompatibleEndpoint::identity($existing->base_url)
                !== OpenAiCompatibleEndpoint::identity($baseUrl);
        } catch (InvalidArgumentException) {
            return true;
        }
    }
}
