<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankLinkSessionVerifyRequest extends FormRequest
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
            'amount_first_cents' => ['required', 'integer', 'min:1', 'max:99'],
            'amount_second_cents' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
