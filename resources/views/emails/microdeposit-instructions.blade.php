<x-mail::message>
# {{ __('Verify your bank account') }}

{{ __('Micro-deposits were sent for bank link :link. Enter the two amounts (in cents) in Budera to finish linking.', ['link' => $bankLink->public_id]) }}

@if(count($amountsCents) === 2)
<x-mail::panel>
{{ __('Expected amounts: :a¢ and :b¢ (in either order).', ['a' => $amountsCents[0], 'b' => $amountsCents[1]]) }}
</x-mail::panel>
@endif

@if(is_string($documentation) && $documentation !== '')
<x-mail::panel>
{{ $documentation }}
</x-mail::panel>
@endif

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
