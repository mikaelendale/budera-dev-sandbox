<x-mail::message>
# {{ __('Payment needs approval') }}

{{ __('A payment from wallet :wallet is waiting for your decision.', ['wallet' => $wallet->public_id]) }}

@if($amountUsd !== '')
{{ __('Amount: :amount USD', ['amount' => $amountUsd]) }}
@endif

@if(is_string($payeeRef) && $payeeRef !== '')
{{ __('Payee: :ref', ['ref' => $payeeRef]) }}
@endif

<x-mail::button :url="$approvalUrl">
{{ __('Review payment') }}
</x-mail::button>

{{ __('Sign in to your Budera account if prompted. You can also approve or deny from this page.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
