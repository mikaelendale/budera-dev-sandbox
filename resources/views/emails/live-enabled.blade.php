<x-mail::message>
# {{ __('Live access enabled') }}

{{ __('Production access is now enabled for :company. You can create live API keys, webhooks, and use the live environment from your company dashboard.', ['company' => $company->name]) }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
