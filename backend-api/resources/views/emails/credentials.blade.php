<x-mail::message>
@include('emails._eyebrow', ['label' => 'Your account is ready', 'tone' => 'blue'])

# Welcome to VMS, {{ $firstName }}

{{ $createdBy }} created a Vehicle Management System (VMS) account for you as **{{ $user->role }}** of **{{ $place }}**.

@include('emails._details', ['title' => 'Your sign-in details', 'rows' => [
    'Email' => $user->email,
    'Temporary password' => $temporaryPassword,
    'Valid until' => $expiresAt->timezone('Asia/Manila')->format('F j, Y g:i A'),
]])

@include('emails._steps', ['title' => 'To get started', 'steps' => [
    'Go to the VMS sign-in page ('.$loginUrl.') and sign in with your email and the temporary password above.',
    'You will be asked to choose your own password right away.',
    'After that, the temporary password no longer works.',
]])

@include('emails._notice', ['tone' => 'red', 'title' => 'Keep this email private', 'text' => 'Do not share the temporary password with anyone. If it expires, ask the person who created your account to send a new one.'])

<x-mail::button :url="$loginUrl">
Sign in to VMS
</x-mail::button>

@include('emails._signoff')
</x-mail::message>
