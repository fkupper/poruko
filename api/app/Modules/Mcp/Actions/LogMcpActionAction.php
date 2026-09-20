<?php

namespace App\Modules\Mcp\Actions;

use App\Enums\McpOperation;
use App\Models\McpActionLog;
use App\Models\User;

final class LogMcpActionAction
{
    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        User $user,
        ?int $ledgerId,
        string $toolName,
        McpOperation $operation,
        array $payload,
        string $status,
        int $durationMs,
    ): McpActionLog {
        return McpActionLog::query()->create([
            'user_id' => $user->id,
            'ledger_id' => $ledgerId,
            'tool_name' => $toolName,
            'operation' => $operation->value,
            'request_payload' => $this->redact($payload),
            'response_status' => $status,
            'duration_ms' => max(0, $durationMs),
            'created_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            $name = mb_strtolower((string) $key);

            if (preg_match('/token|password|secret|authorization|cookie/i', $name) === 1) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $redacted[$key] = $this->redact($nested);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
