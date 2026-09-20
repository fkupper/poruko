<?php

namespace App\Http\Resources;

use App\Enums\McpPostMode;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ledger
 */
class LedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency ?? config('currencies.default', 'EUR'),
            'currency_symbol' => config("currencies.available.{$this->currency}.symbol", '€'),
            'settlement_mode' => $this->settlement_mode,
            'settlement_timezone' => $this->settlement_timezone,
            'settlement_cutoff_day' => $this->settlement_cutoff_day,
            'settlement_cutoff_time' => $this->settlement_cutoff_time,
            'settlement_auto_execute_enabled' => $this->settlement_auto_execute_enabled,
            'users_count' => $this->whenCounted('users'),
            'my_preferences' => $this->when($this->pivot !== null, function () {
                return [
                    'main_personal_account_id' => $this->pivot->main_personal_account_id,
                    'default_payment_account_id' => $this->pivot->default_payment_account_id,
                    'default_expense_account_id' => $this->pivot->default_expense_account_id,
                    'mcp' => [
                        'enabled' => (bool) $this->pivot->mcp_enabled,
                        'allow_read' => (bool) ($this->pivot->mcp_allow_read ?? true),
                        'allow_write' => (bool) $this->pivot->mcp_allow_write,
                        'allow_destructive' => (bool) $this->pivot->mcp_allow_destructive,
                        'post_mode' => $this->pivot->mcp_post_mode instanceof McpPostMode
                            ? $this->pivot->mcp_post_mode->value
                            : (string) ($this->pivot->mcp_post_mode ?? 'approval_queue'),
                    ],
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
