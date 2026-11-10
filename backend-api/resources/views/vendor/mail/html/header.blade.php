@props(['url', 'logo' => null])
<tr>
<td class="header">
<table class="header-band" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
{{-- Gear logo is embedded inline (CID) so it shows without "load images"; the wordmark stays as text. --}}
<table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
@if ($logo)
<td style="padding: 0 10px 0 0; vertical-align: middle;"><img src="{{ $logo }}" width="40" height="40" alt="" style="display: block; border: 0;"></td>
@endif
<td style="padding: 0; vertical-align: middle;"><p class="header-wordmark">vms</p></td>
</tr>
</table>
<p class="header-tagline">Vehicle Management System</p>
</td>
</tr>
</table>
</td>
</tr>
