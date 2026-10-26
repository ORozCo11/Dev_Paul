<x-mail::message>
# Your VMS account is now active

Hi **{{ $firstName }}**,

Your VMS account is now active. You are now the Administrator for **{{ $place }}**.

<x-mail::button :url="$loginUrl">
Sign in to VMS
</x-mail::button>

VMS Support Center<br>
{{ config('mail.from.address') }}
</x-mail::message>
