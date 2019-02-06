{*
* Copyright (c) 2019 PayGate (Pty) Ltd
*
* Author: App Inlet (Pty) Ltd
* 
* Released under the GNU General Public License
*}

<script type="text/javascript">
    window.onload = function () {
        document.forms["vcard"].submit();
    }
</script>

<form action="{$paysubsGatewayUrl}" method="post" name="vcard" id="vcard" class="hidden">
	<input type="hidden" name="p1" value="{$p1}" />
	<input type="hidden" name="p2" value="{$p2}" />
	<input type="hidden" name="p3" value="{$p3}" />
	<input type="hidden" name="p4" value="{$p4}" />
	<input type="hidden" name="p5" value="{$p5}" />
	<input type="hidden" name="p10" value="{$p10}" />
	<input type="hidden" name="Budget" value="{$Budget}" />
	<input type="hidden" name="m_5" value="{$m_5}" />
	<input type="hidden" name="osCommerce" value="{$osCommerce}" />
	<input type="hidden" name="osApprovedUrl" value="{$osApprovedUrl}" />
	<input type="hidden" name="osDeclinedUrl" value="{$osDeclinedUrl}" />
	<input type="hidden" name="cardholderemail" value="{$cardholderemail}" />
	{if $Hash}
	<input type="hidden" name="Hash" value="{$Hash}" />
	{/if}
</form>