<x-mail::message>
# {{ __('KYB approved') }}

{{ __('Good news — :company has passed KYB review.', ['company' => $company->name]) }}

{{ __('Request live access from your company settings. After Budera enables production access, you can create live API keys and use the live environment.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
