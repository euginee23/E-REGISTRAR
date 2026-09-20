<x-mail::message>
# {{ $type->label() }}

@if ($recipientName !== null)
{{ __('Hello :name,', ['name' => $recipientName]) }}
@endif

{{ $body }}

@if ($url !== null)
<x-mail::button :url="$url">
{{ __('Open in e-Registrar') }}
</x-mail::button>
@endif

{{ __('You are receiving this because you have an e-Registrar account. The same update is waiting for you when you sign in.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
