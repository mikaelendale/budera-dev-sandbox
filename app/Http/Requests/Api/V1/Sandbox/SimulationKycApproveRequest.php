<?php

namespace App\Http\Requests\Api\V1\Sandbox;

use Illuminate\Foundation\Http\FormRequest;

class SimulationKycApproveRequest extends FormRequest
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
            'wallet_kyc_verification_id' => ['required', 'integer', 'exists:wallet_kyc_verifications,id'],
        ];
    }
}
