<x-mail::message>
# {{ __('More information needed') }}

{{ __('We need additional information to continue verification for wallet :wallet.', ['wallet' => $wallet->public_id]) }}

{{ __('Open your Budera dashboard to review next steps.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
