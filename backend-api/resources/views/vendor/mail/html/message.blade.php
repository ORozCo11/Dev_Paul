<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')" logo="cid:vms-gear">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Barangay Vehicle Management System. All rights reserved.<br>
Sent by the VMS Support Center · {{ config('mail.from.address') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
