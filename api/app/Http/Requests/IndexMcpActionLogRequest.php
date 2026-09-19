<?php

namespace App\Http\Requests;

use App\Models\Ledger;
use App\Models\McpActionLog;
use Illuminate\Foundation\Http\FormRequest;

class IndexMcpActionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ledger = $this->route('ledger');

        return $ledger instanceof Ledger
            && $this->user()?->can('viewAny', [McpActionLog::class, $ledger]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
