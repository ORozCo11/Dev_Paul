<x-mail::message>
@include('emails._eyebrow', ['label' => 'Registration not approved', 'tone' => 'red'])

# {{ $subjectLine }}

Hi **{{ $firstName }}**,

@include('emails._body-card', ['body' => $body])

@include('emails._notice', ['tone' => 'red', 'title' => 'Reason', 'text' => $reason])

@include('emails._details', ['title' => 'Registration details', 'rows' => [
    'Name' => $user->name,
    'Email' => $user->email,
    'Barangay' => $place,
]])

If you believe this was a mistake, you can reply to this email or register again with the correct details.

@if ($showLinks)
<x-mail::button :url="$registerUrl">
Register again
</x-mail::button>
@endif

@include('emails._signoff')
</x-mail::message>
