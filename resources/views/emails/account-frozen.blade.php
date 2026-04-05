<x-mail::message>
# {{ __('Wallet frozen') }}

{{ __('Wallet :wallet has been frozen. Outbound payments and transfers are paused until the account is unfrozen.', ['wallet' => $wallet->public_id]) }}

{{ __('If you have questions, contact your organization administrator or Budera support.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
