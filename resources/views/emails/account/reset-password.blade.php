@component('mail::message')
# Password Reset

A password reset has been requested for the following account:: {{ $email }}
If this was a mistake, just ignore this email and nothing will happen.
To reset your password, visit the following address: [Click to reset your password]({{ $resetLink }}).
This link is available for 24 hours since it was sent.

Regards, {{ $website }} team.

@endcomponent
