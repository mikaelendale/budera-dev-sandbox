<x-mail::message>
# {{ __('Insufficient wallet balance') }}

{{ __('A payment of :amount¢ could not be sent because wallet :wallet only has :balance¢ available.', ['amount' => $amountCents, 'wallet' => $wallet->public_id, 'balance' => $balanceCents]) }}

{{ __('Add funds via a top-up from your linked bank account, then try again.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
