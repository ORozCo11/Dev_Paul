<x-mail::message>
# Your VMS registration, {{ $firstName }}

Thank you for your interest in the Vehicle Management System (VMS) for **{{ $place }}**.

We are sorry to inform you that we were **not able to approve your registration** at this time. We could not verify that the details you submitted match a person authorized to use VMS for this barangay.

**What you can do:**

1. Register again using your **real, full name** and an email address you can access.
2. Have a **valid government-issued ID** and a **clear, recent photo of yourself** ready, in case the Super Admin or your barangay office asks you to verify your identity.
3. Confirm with your barangay office that you are expected to hold the role you register for.

<x-mail::button :url="$registerUrl">
Register again
</x-mail::button>

If you believe this was a mistake, please reach out through the Support Center or contact us at {{ config('mail.from.address') }}, and include your barangay and contact details.

We appreciate your understanding.

VMS Support Center

<small>This is an automated message. Please do not share your password with anyone, including VMS staff.</small>
</x-mail::message>
