<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBankLinkRequest extends FormRequest
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
            'amount_first_cents' => ['required', 'integer', 'min:1', 'max:999'],
            'amount_second_cents' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
