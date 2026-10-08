<x-mail::message>
# {{ __('Test email') }}

{{ __('This message confirms that e-Registrar can send email. If you are reading it, the mail settings are working.') }}

<x-mail::panel>
{{ __('Sent by: :name', ['name' => $sentBy]) }}<br>
{{ __('Mailer: :mailer', ['mailer' => $mailer]) }}<br>
{{ __('Sent at: :time', ['time' => $sentAt]) }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
