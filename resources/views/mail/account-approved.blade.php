<x-mail::message>
# {{ __('Your account is ready') }}

{{ __('Hello :name,', ['name' => $user->name]) }}

{{ __('The registrar has reviewed and approved your e-Registrar account. You can now sign in to request documents and book a claiming appointment.') }}

<x-mail::button :url="$loginUrl">
{{ __('Sign in') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
