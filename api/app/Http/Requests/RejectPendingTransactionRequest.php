<?php

namespace App\Http\Requests;

use App\Models\PendingTransaction;
use Illuminate\Foundation\Http\FormRequest;

class RejectPendingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pendingTransaction = $this->route('pendingTransaction');

        return $pendingTransaction instanceof PendingTransaction
            && $this->user()?->can('reject', $pendingTransaction) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
