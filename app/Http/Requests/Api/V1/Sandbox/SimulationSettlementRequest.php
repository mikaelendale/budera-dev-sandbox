<?php

namespace App\Http\Requests\Api\V1\Sandbox;

use Illuminate\Foundation\Http\FormRequest;

class SimulationSettlementRequest extends FormRequest
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
            'bank_transfer_id' => ['nullable', 'string', 'max:128', 'required_without_all:payment_id,topup_id'],
            'payment_id' => ['nullable', 'string', 'max:128', 'required_without_all:bank_transfer_id,topup_id'],
            'topup_id' => ['nullable', 'string', 'max:128', 'required_without_all:bank_transfer_id,payment_id'],
        ];
    }
}
