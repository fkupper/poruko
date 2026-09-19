<?php

namespace App\Http\Resources;

use App\Enums\McpPostMode;
use App\Models\LedgerUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerUser
 */
class McpSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $postMode = $this->mcp_post_mode instanceof McpPostMode
            ? $this->mcp_post_mode->value
            : (string) ($this->mcp_post_mode ?? McpPostMode::ApprovalQueue->value);

        return [
            'enabled' => (bool) $this->mcp_enabled,
            'allow_read' => (bool) $this->mcp_allow_read,
            'allow_write' => (bool) $this->mcp_allow_write,
            'allow_destructive' => (bool) $this->mcp_allow_destructive,
            'post_mode' => $postMode,
        ];
    }
}
