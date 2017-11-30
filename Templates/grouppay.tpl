<script type="application/javascript">
function checkAmt(limit){
    var el = document.getElementsByName('amount');

	if(!(/[0-9]*\.?[0-9]+/.test(el[0].value))){
		alert('Please Enter Valid Amount');
		return false;
	}else{
		if(parseFloat(el[0].value) < parseFloat(limit)){
			alert('Please enter an amount higher than the minimum payment of ' + limit);
			return false;
		}
	}
}
</script>

{if $grouppayActive}
	{* Group Pay is Active *}
	{* REPLACE WITH GENERIC INFO ABOUT GROUP YOU WANT BOTH LOGGED IN CLIENTS AND PAYERS (Loged out clients) TO SEE *}
	{if $fromPayPal}
		{* Screen shown when returning from a paypal transaction *}
		<p>Your payment has been submitted to paypal.<br>You will be emailed a confirmation from PayPal with the details.</p>
	{else}
		{if $loggedin and ! $anotherClientHash}
			{* This is shown to Logged in clients that haven't supplied another client's hash *}
			Your {$SystemName} hash is: <b>{$loggedInHash}</b><br>
			Your {$SystemName} link is:
            <b>
                <a href="{rtrim($systemurl, '/')}/grouppay.php?hash={$loggedInHash}">
                    {rtrim($systemurl, '/')}/grouppay.php?hash={$loggedInHash}
                </a>
            </b>
            <br>
			<br>

			{* DELETE THE BELOW CODE TO REMOVE PAST PAYMENTS SHOWING FOR LOGGED IN CLIENTS *}

				<h2>Past {$SystemName} Payments</h2>
				<p>Below are payments that have been made to your account from others.{if $hidePublicPayments} These are only shown to you.{/if}</p>

				{if empty($pastPayments)}
					<b>No previous payments found.</b>
				{else}
					<table class="data" style="width:100%">
						<tr><th>Date</th><th>Paid By</th><th>Amount</th></tr>
						{foreach from=$pastPayments key=myId item=pmnt}
							<tr><td>{$pmnt.date}</td><td>{trim($pmnt.description, "() \t\n\r\0\x0B")}</td><td>{$pmnt.amount}</td></tr>
						{/foreach}
					</table>
				{/if}
			{*  DELETE THE ABOVE CODE TO REMOVE PAST PAYMENTS SHOWING FOR LOGGED IN CLIENTS *}

		{else}
			{* This is shown to Payers (clients that are not logged-in or logged-in clients that gave another hash) *}
			{if $clientFound}
				{* Payer Has Provided a valid hash *}
				<b>Client Hash:</b> {$clientHash}<br>
				<b>Client Name:</b> {$clientInfo->firstname} {$clientInfo->lastname}
                {if !empty($clientInfo->company)} ({$clientInfo->company}){/if}<br>
				<b>Minimum Payment:</b> {formatCurrency($minPayment)}<br>

                <form id="paypalForm" action="{$payPalUrl}" onsubmit="return checkAmt({$minPayment})" method="post">
                    <input type="hidden" name="cmd" value="_xclick">
                    <input type="hidden" name="custom" value="{$clientHash}">
                    <input type="hidden" name="no_note" value="1">
                    <input type="hidden" name="item_name" value="{$companyname} - {$SystemName} - {$clientInfo->firstname} {$clientInfo->lastname}{if !empty($clientInfo->company)} ({$clientInfo->company}){/if}">
                    <input type="hidden" name="currency_code" value="{$currency.code}">
                    <input type="hidden" name="return" value="{rtrim($systemurl, '/')}/grouppay.php?fromPaypal=true">
                    <input type="hidden" name="cancel_return" value="{rtrim($systemurl, '/')}/grouppay.php?hash={$clientHash}">
                    <input type="hidden" name="notify_url" value="{rtrim($systemurl, '/')}/modules/addons/group_pay/grouppay_callback.php">
                    <input type="hidden" name="no_shipping" value="1">
                    <input type="hidden" name="business" value="{$gpPayPalEmail}">
                    <b>Payment Amount:</b> <input type="text" title="amount" name="amount"/><br>
                    <input type="image" class="gppaypalimage" style="height:40px; width:145px; border:none; margin-top: 16px" src="https://www.paypalobjects.com/en_US/i/btn/btn_xpressCheckout.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
                </form>

				{if ! $hidePublicPayments}

					<h2>Past {$SystemName} Payments</h2>
					<p>Below are payments that have been made to this client's {$SystemName}.</p>
					<table class="data" style="width:100%">
						<tr><th>Date</th><th>Description</th><th>Amount</th></tr>
						{foreach from=$pastPayments key=myId item=pmnt}
							<tr><td>{$pmnt.date}</td><td>{$pmnt.description}</td><td>{$pmnt.amount}</td></tr>
						{/foreach}
					</table>

				{/if}

			{else}
				{* Payer Has Provided an invalid hash *}
				You have provided a bad client hash.<br>
				Please contact the person that gave you the hash to check the value.
			{/if}
		{/if}
	{/if}
{else}
	{* Group Pay is Inactive *}
	{* NOTE: If your license is invalid the system is forced into disabled mode *}
	<p>The group pay system is currently not enabled.<br>Please contact support for more info.</p>
{/if}
