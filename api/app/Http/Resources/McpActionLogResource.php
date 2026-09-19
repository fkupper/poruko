<?php

namespace App\Http\Resources;

use App\Models\McpActionLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin McpActionLog
 */
class McpActionLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'ledger_id' => $this->ledger_id,
            'tool_name' => $this->tool_name,
            'operation' => $this->operation,
            'request_payload' => $this->request_payload,
            'response_status' => $this->response_status,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
