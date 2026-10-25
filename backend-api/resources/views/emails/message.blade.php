<x-mail::message>
Hi **{{ $firstName }}**,

{{-- The barangay is bolded wherever the message names it. --}}
{!! nl2br(str_replace(e($place), "<strong>".e($place)."</strong>", e($body))) !!}

@isset($code)
@include('emails._code')
@endisset

VMS Support Center<br>
{{ config('mail.from.address') }}
</x-mail::message>
