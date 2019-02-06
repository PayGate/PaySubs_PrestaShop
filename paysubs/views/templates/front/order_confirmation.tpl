{*
* Copyright (c) 2019 PayGate (Pty) Ltd
*
* Author: App Inlet (Pty) Ltd
* 
* Released under the GNU General Public License
*}

{if $status == 'ok'}
	<p>{$message_display}<br /><br />{l s='For any questions or for further information, please contact our' mod='paysubs'} <a href="{$base_dir}index.php?controller=contact">{l s='customer support' mod='paysubs'}</a>.
	</p>
	You can view your order or download any media files associated with said order by viewing your <br/><br/> <a href="{$base_dir}index.php?controller=history" class="button_large">{l s='Order History' mod='paysubs'}</a><br/> which can be accessed via clicking the My Orders link on the left hand side of the page, and then clicking on the details of the order in question. Emails have been sent to you confirming your order, with. In the case of a downloadable product, an email with a download link has been sent.
	<br />
{else}
	<p class="warning">
		{$message_display}<br/><br/>{l s=' You are welcome to contact our' mod='paysubs'} 
		<a href="{$base_dir}index.php?controller=contact">{l s='customer support team ' mod='paysubs'}</a>
        {l s='if you require further assistance.' mod='paysubs'}
	</p>
    <p class="cart_navigation">
		<a href="{$base_dir}index.php?controller=order&step=3" class="button_large">{l s='Other payment methods' mod='paysubs'}</a>
    </p>

{/if}