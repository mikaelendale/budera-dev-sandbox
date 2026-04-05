<?php

namespace App\Http\Requests\Api\V1\Sandbox;

use Illuminate\Foundation\Http\FormRequest;

class SimulationMicrodepositRequest extends FormRequest
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
            'bank_link_id' => ['required', 'string', 'max:64'],
        ];
    }
}
