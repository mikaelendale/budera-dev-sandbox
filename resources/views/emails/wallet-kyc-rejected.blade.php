<x-mail::message>
# {{ __('Verification could not be completed') }}

{{ __('We could not verify identity for wallet :wallet.', ['wallet' => $wallet->public_id]) }}

{{ __('Please review your submission or start a new verification from your dashboard.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
