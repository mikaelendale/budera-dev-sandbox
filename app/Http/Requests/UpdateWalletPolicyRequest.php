<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWalletPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $nullableNumeric = [
            'per_tx_limit_usd',
            'daily_spend_limit_usd',
            'daily_tx_count',
            'require_approval_above',
            'approval_timeout_secs',
            'max_new_payees_per_day',
        ];

        $merged = [];
        foreach ($nullableNumeric as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        if ($this->has('agent_type') && $this->input('agent_type') === '') {
            $merged['agent_type'] = null;
        }

        foreach (['allowed_categories', 'blocked_payees', 'auto_topup'] as $jsonKey) {
            if (! $this->has($jsonKey)) {
                continue;
            }
            $raw = $this->input($jsonKey);
            if ($raw === '' || $raw === null) {
                $merged[$jsonKey] = null;

                continue;
            }
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $merged[$jsonKey] = is_array($decoded) ? $decoded : [];
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_type' => ['nullable', 'string', 'max:255'],
            'per_tx_limit_usd' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'daily_spend_limit_usd' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'daily_tx_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'allowed_categories' => ['nullable', 'array'],
            'allowed_categories.*' => ['string', 'max:255'],
            'blocked_payees' => ['nullable', 'array'],
            'blocked_payees.*' => ['string', 'max:512'],
            'require_approval_above' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'approval_timeout_secs' => ['nullable', 'integer', 'min:1', 'max:2592000'],
            'max_new_payees_per_day' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'business_hours_only' => ['sometimes', 'boolean'],
            'velocity_sensitivity' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'auto_topup' => ['nullable', 'array'],
        ];
    }
}
