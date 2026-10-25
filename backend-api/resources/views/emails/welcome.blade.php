<x-mail::message>
# Welcome to VMS, {{ $firstName }}

Thank you for registering with the Vehicle Management System (VMS) as the first Administrator for **{{ $place }}**.

**Your account has been created, but it is not active yet.**

Because you are the first Administrator for your barangay, the VMS Super Admin needs to confirm your appointment with your barangay office. Once you are confirmed, we will send your **Staff Registration Code** to this email address.

**What happens next:**

1. The Super Admin reviews your registration. You do not need to do anything yet.
2. Watch this inbox, including your Spam or Promotions folder, for a second email with your Staff Registration Code.
3. You will use that code to activate your account.

**Your registration details:**

- Name: **{{ $user->name }}**
- Email: {{ $user->email }}
- Barangay: **{{ $place }}**
- Submitted: {{ $user->created_at->timezone('Asia/Manila')->format('F j, Y g:i A') }}

If you did not register for VMS, you can ignore this email, or contact us at {{ config('mail.from.address') }}.

VMS Support Center

<small>This is an automated message. Please do not share your password with anyone, including VMS staff.</small>
</x-mail::message>
