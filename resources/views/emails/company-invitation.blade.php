<x-mail::message>
# {{ __('Invitation to :company', ['company' => $companyName]) }}

{{ __('You have been invited to join :company on Budera.', ['company' => $companyName]) }}

{{ __('Sign in or register using this email address: :email', ['email' => $inviteeEmail]) }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('Full link (includes the invitation token):') }}

<x-mail::panel>
{{ $acceptUrl }}
</x-mail::panel>

{{ __('You can also paste only the token on the onboarding “Invitation” tab after you sign in.') }}

{{ __('If you did not expect this message, you can ignore it.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
