@component('mail::message')
# Account delete confirmation

Hello {{ $name }},
You made a request to delete your account on our website: {{ $website }}.

Please click [this link]({{ $deleteLink }}) to confirm the deletion of your account.
This link is available for 24 hours since it was sent.

If you did not made this request please contact us.

@endcomponent