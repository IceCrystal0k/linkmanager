@component('mail::message')
# Invitation to become a contributor to the company

Hello {{ $name }},
The company {{ $company }} invited you to become a user for their billing process.

Please click [this link]({{ $acceptLink }}) to confirm that you accept the invitation.
This link is available for 24 hours since it was sent.

If you do not know why you were invited, then ignore this email.

@endcomponent