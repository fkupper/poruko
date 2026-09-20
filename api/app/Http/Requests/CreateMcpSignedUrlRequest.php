<?php

namespace App\Http\Requests;

use App\Modules\Mcp\Services\McpTokenService;
use Illuminate\Foundation\Http\FormRequest;

class CreateMcpSignedUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expires_in_hours' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . McpTokenService::MAX_SIGNED_URL_HOURS,
            ],
        ];
    }
}
