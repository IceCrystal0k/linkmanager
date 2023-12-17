@component('mail::message')
# Verify your email

Hello {{ $name }},
You registered on our website: {{ $website }}.

Please click [this link]({{ $verifyLink }}) to complete the registration process.
This link is available for 24 hours since it was sent.

Regards, {{ $website }} team.

@endcomponent
