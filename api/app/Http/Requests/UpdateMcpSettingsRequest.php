<?php

namespace App\Http\Requests;

use App\Enums\McpPostMode;
use App\Models\Ledger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMcpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('view', $ledger) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'allow_read' => ['required', 'boolean'],
            'allow_write' => ['required', 'boolean'],
            'allow_destructive' => ['required', 'boolean'],
            'post_mode' => ['required', Rule::enum(McpPostMode::class)],
            'destructive_ack' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('allow_destructive') && !$this->boolean('destructive_ack')) {
                $validator->errors()->add(
                    'destructive_ack',
                    'You must acknowledge the risks to enable destructive MCP operations.',
                );
            }
        });
    }
}
