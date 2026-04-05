<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTopupRequest extends FormRequest
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
            'bank_link_id' => [
                'required',
                'string',
                Rule::exists('bank_links', 'public_id')->where('status', 'verified'),
            ],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'authorization_ledger_entry_id' => [
                'nullable',
                'integer',
                Rule::exists('authorization_ledger', 'id'),
            ],
        ];
    }
}
