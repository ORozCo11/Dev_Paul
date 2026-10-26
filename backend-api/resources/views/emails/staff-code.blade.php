<x-mail::message>
# Your Staff Registration Code

Hi **{{ $firstName }}**,

Your appointment as the Administrator for **{{ $place }}** has been confirmed. Here is your Staff Registration Code:

@include('emails._code')

**This code expires on {{ $expiresAt->timezone('Asia/Manila')->format('F j, Y g:i A') }} (7 days from today).**

**To activate your account:**

1. Go to the VMS sign-in page and sign in with your email and password.
2. When the "Enter your registration code" window appears, paste the code above.
3. Click **Activate**. You will then have full access as your barangay's Administrator.

**Keep this code private.** It gives Administrator access to your barangay. VMS staff will never ask you for it.

If the code expires before you use it, your registration will be cancelled and you will need to register again.

VMS Support Center<br>
{{ config('mail.from.address') }}
</x-mail::message>
