<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
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
            'from_wallet_account_id' => ['required', 'string', 'exists:wallet_accounts,public_id'],
            'to_wallet_account_id' => ['required', 'string', 'exists:wallet_accounts,public_id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }
}
