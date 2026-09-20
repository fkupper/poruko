<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateMcpSignedUrlRequest;
use App\Http\Requests\StoreMcpTokenRequest;
use App\Http\Resources\McpTokenResource;
use App\Models\User;
use App\Modules\Mcp\Services\McpTokenService;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class McpTokenController extends Controller
{
    public function __construct(private readonly McpTokenService $tokens) {}

    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return McpTokenResource::collection($this->tokens->listFor($user))
            ->additional([
                'meta' => [
                    'mcp_url' => $this->tokens->endpointUrl(),
                    'authorization_header' => 'Authorization',
                ],
            ])
            ->response();
    }

    public function store(StoreMcpTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $accessToken = $this->tokens->create($user, (string) $request->validated('name'));
        $plainTextToken = $accessToken->plainTextToken;
        $signed = $this->tokens->signedUrl($accessToken->accessToken);

        return McpTokenResource::make($accessToken->accessToken)
            ->additional([
                'meta' => [
                    'token' => $plainTextToken,
                    'mcp_url' => $this->tokens->endpointUrl(),
                    'authorization_header' => 'Authorization',
                    'client_config' => $this->tokens->clientConfig($plainTextToken),
                    'signed_url' => $signed,
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(PersonalAccessToken $mcpToken): JsonResponse
    {
        $this->ownedMcpToken($mcpToken)->delete();

        return response()->json([
            'message' => 'MCP key revoked.',
        ]);
    }

    public function signedUrl(CreateMcpSignedUrlRequest $request, PersonalAccessToken $mcpToken): JsonResponse
    {
        $token = $this->ownedMcpToken($mcpToken);
        $hours = (int) ($request->validated('expires_in_hours') ?? McpTokenService::DEFAULT_SIGNED_URL_HOURS);

        return response()->json([
            'data' => $this->tokens->signedUrl($token, $hours),
        ]);
    }

    private function ownedMcpToken(PersonalAccessToken $mcpToken): PersonalAccessToken
    {
        /** @var User $user */
        $user = request()->user();

        if (!$this->tokens->belongsTo($user, $mcpToken)) {
            throw new NotFoundHttpException('MCP key not found.');
        }

        return $mcpToken;
    }
}
