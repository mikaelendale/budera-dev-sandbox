<x-mail::message>
# {{ __('Wallet verified') }}

{{ __('Good news — identity verification for wallet :wallet is complete and the wallet is active.', ['wallet' => $wallet->public_id]) }}

{{ __('You can fund and use this wallet from your Budera dashboard or API.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
