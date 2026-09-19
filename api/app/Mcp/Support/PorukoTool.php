<?php

namespace App\Mcp\Support;

use App\Enums\McpOperation;
use App\Models\Ledger;
use App\Models\User;
use App\Modules\Ledger\Exceptions\InvalidLedgerPostingException;
use App\Modules\Ledger\Exceptions\PendingTransactionReviewException;
use App\Modules\Mcp\Actions\LogMcpActionAction;
use App\Modules\Mcp\Exceptions\McpAuthorizationException;
use App\Modules\Mcp\Services\McpCapabilityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

abstract class PorukoTool extends Tool
{
    abstract protected function operation(): McpOperation;

    /**
     * @return array<string, mixed>
     */
    abstract protected function run(Request $request, User $user, ?Ledger $ledger): array;

    protected function requiresLedger(): bool
    {
        return true;
    }

    protected function requiresMcpEnabled(): bool
    {
        return true;
    }

    protected function requiresCapability(): bool
    {
        return true;
    }

    public function name(): string
    {
        return (string) Str::of(parent::name())->replaceEnd('-tool', '');
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $startedAt = microtime(true);
        $status = 'error';
        $user = $request->user();
        $ledger = null;

        try {
            if (!$user instanceof User) {
                $status = 'unauthenticated';

                return Response::error('Authentication is required.');
            }

            if ($this->requiresLedger()) {
                $ledger = $this->resolveLedger($request, $user);
            }

            if ($this->requiresCapability()) {
                app(McpCapabilityService::class)->assert(
                    $user,
                    $ledger,
                    $this->operation(),
                    $this->requiresMcpEnabled(),
                );
            } elseif ($ledger instanceof Ledger) {
                app(McpCapabilityService::class)->membership($user, $ledger);
            }

            if ($ledger instanceof Ledger) {
                setPermissionsTeamId($ledger->id);
            }

            $payload = $this->run($request, $user, $ledger);
            $status = 'ok';

            return Response::structured($payload);
        } catch (ValidationException $exception) {
            $status = 'invalid';
            $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();

            return Response::error((string) $message);
        } catch (McpAuthorizationException|AuthorizationException $exception) {
            $status = 'denied';

            return Response::error($exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'This action is unauthorized.');
        } catch (InvalidLedgerPostingException|PendingTransactionReviewException $exception) {
            $status = 'invalid';

            return Response::error($exception->getMessage());
        } catch (ModelNotFoundException $exception) {
            $status = 'not_found';

            return Response::error('The requested record was not found in this space.');
        } catch (HttpException $exception) {
            $status = $exception->getStatusCode() === 403 ? 'denied' : 'error';

            return Response::error($exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'The request could not be completed.');
        } catch (Throwable $exception) {
            $status = 'error';

            report($exception);

            return Response::error('The MCP action failed. Please try again.');
        } finally {
            if ($user instanceof User) {
                app(LogMcpActionAction::class)->execute(
                    $user,
                    $ledger?->id,
                    $this->name(),
                    $this->operation(),
                    $request->toArray(),
                    $status,
                    (int) round((microtime(true) - $startedAt) * 1000),
                );
            }
        }
    }

    protected function resolveLedger(Request $request, User $user): Ledger
    {
        $validated = $request->validate([
            'ledger_id' => ['required', 'integer'],
        ], [
            'ledger_id.required' => 'Provide ledger_id for the space you want to act in.',
        ]);

        $ledger = Ledger::query()->find((int) $validated['ledger_id']);

        if (!$ledger instanceof Ledger || $user->cannot('view', $ledger)) {
            throw new McpAuthorizationException('You cannot access this space.');
        }

        return $ledger;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resourceArray(JsonResource $resource): array
    {
        return $resource->resolve();
    }

    /**
     * @param iterable<int, mixed> $resources
     * @return array<int, array<string, mixed>>
     */
    protected function resourceCollection(iterable $resources): array
    {
        return JsonResource::collection($resources)->resolve();
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function ledgerIdSchema(JsonSchema $schema): array
    {
        return [
            'ledger_id' => $schema->integer()
                ->description('The space (ledger) id.')
                ->required(),
        ];
    }
}
