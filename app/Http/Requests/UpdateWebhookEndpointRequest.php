<?php

namespace App\Http\Requests;

use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $endpoint = $this->route('webhookEndpoint');

        return $endpoint instanceof WebhookEndpoint
            && ($this->user()?->can('update', $endpoint) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        $allowed = config('budera.outbound_webhook_events', []);

        return [
            'url' => ['sometimes', 'required', 'string', 'max:2048', 'regex:/^https:\/\/.+/i'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'max:128', Rule::in(array_merge($allowed, ['*']))],
            'environment' => ['sometimes', 'required', 'in:sandbox,live'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
