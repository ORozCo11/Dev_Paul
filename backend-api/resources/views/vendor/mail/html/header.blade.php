@props(['url'])
<tr>
<td class="header">
<table class="header-band" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
{{-- Email clients ignore web fonts, so the gear + "vms" in Anta is one linked PNG (config mail.logo_url); its alt text keeps "vms" readable when images are off. --}}
<table class="header-brand" align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@if (config('mail.inline_logo'))
<td class="header-gear"><img src="cid:vms-gear@vms" width="36" height="36" alt=""></td>
<td><p class="header-wordmark">vms</p></td>
@elseif (config('mail.logo_url'))
<td><img src="{{ config('mail.logo_url') }}" width="131" height="48" alt="vms" class="header-brand-image"></td>
@else
<td><p class="header-wordmark">vms</p></td>
@endif
</tr>
</table>
<p class="header-tagline">Vehicle Management System</p>
</td>
</tr>
</table>
</td>
</tr>
