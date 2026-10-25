<x-mail::message>
# Your VMS registration has expired

Hi **{{ $firstName }}**,

Your Staff Registration Code for **{{ $place }}** expired on {{ $expiresAt->timezone('Asia/Manila')->format('F j, Y g:i A') }} without being used, so your registration has been cancelled.

To try again, fill out the registration form.

<x-mail::button :url="$registerUrl">
Register again
</x-mail::button>

VMS Support Center<br>
{{ config('mail.from.address') }}
</x-mail::message>
