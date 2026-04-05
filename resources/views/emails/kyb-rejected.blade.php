<x-mail::message>
# {{ __('KYB review update') }}

{{ __('We reviewed :company and could not approve live access at this time.', ['company' => $company->name]) }}

<x-mail::panel>
{{ $reason }}
</x-mail::panel>

{{ __('You may submit a new review from your dashboard after addressing the items above.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
