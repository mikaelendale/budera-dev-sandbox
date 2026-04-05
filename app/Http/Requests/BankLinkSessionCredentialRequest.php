<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankLinkSessionCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'routing_number' => ['required', 'string', 'regex:/^\d{9}$/'],
            'account_number' => ['required', 'string', 'min:4', 'max:32', 'regex:/^\d+$/'],
            'bank_slug' => ['nullable', 'string', 'max:64'],
        ];
    }
}
