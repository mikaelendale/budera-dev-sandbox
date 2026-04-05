<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'wallet_account_id' => ['required', 'string', 'exists:wallet_accounts,public_id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'payee_ref' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
        ];
    }
}
