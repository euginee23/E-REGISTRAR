<x-mail::message>
# {{ __('Your account request was not approved') }}

{{ __('Hello :name,', ['name' => $user->name]) }}

{{ __('The registrar reviewed your e-Registrar account request and was unable to approve it.') }}

**{{ __('Reason given:') }}** {{ $reason }}

{{ __('If you believe this is a mistake, please visit the registrar\'s office with your student ID so your details can be checked.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
