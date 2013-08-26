<h1>{$SystemName}</h1>
{* REQUIRED *}{$verifyAmtScript}{* REQUIRED *}

{if $grouppayActive}
	{* Group Pay is Active *}
	{* REPLACE WITH GENERIC INFO ABOUT GROUP YOU WANT BOTH LOGGED IN CLIENTS AND PAYERS (Loged out clients) TO SEE *}
	{if $fromPaypal}
		{* Screen shown when returning from a paypal transaction *}
		<p>Your payment has been submitted to paypal.<br>You will be emailed a confirmation from PayPal with the details.</p>
	{else}
		{if $loggedin and ! $anotherClientHash}
			{* This is shown to Logged in clients that haven't supplied another client's hash *}
			Your {$SystemName} hash is: <b>{$loggedInClientHash}</b><br>
			Your {$SystemName} link is: <b><a href="{$hashLink}">{$hashLink}</a></b><br>
			<br/>
			
			{* DELETE THE BELOW CODE TO REMOVE PAST PAYMENTS SHOWING FOR LOGGED IN CLIENTS *}
			
				<h2>Past {$SystemName} Payments</h2>
				<p>Below are payments that have been made to your account from others.{if $hidePublicPayments} These are only shown to you.{/if}</p>
				<table class="data" style="width:100%">
					<tr><th>Date</th><th>Paid By</th><th>Amount</th></tr>
					{foreach from=$pastPayments key=myId item=pmnt}
						<tr><td>{$pmnt.date}</td><td>{$pmnt.description}</td><td>{$pmnt.amount}</td></tr>
					{/foreach}
				</table>
			
			{*  DELETE THE ABOVE CODE TO REMOVE PAST PAYMENTS SHOWING FOR LOGGED IN CLIENTS *}
			
		{else}
			{* This is shown to Payers (clients that are not logged-in or logged-in clients that gave another hash) *}
			{if $clientFound}
				{* Payer Has Provided a valid hash *}	
				<b>Client Hash:</b> {$clientHash}<br>
				<b>Client Name:</b> {$clientInfo.firstname} {$clientInfo.lastname} {if $clientInfo.companyname}({$clientInfo.companyname}){/if}<br>
				<b>Minimum Payment:</b> {$minPayment}<br>		
				{* REQUIRED *} {$gpFormStart} {* REQUIRED *}
				<b>Payment Amount:</b> <input type=textbox name="amount"/><br>		
				{* REQUIRED *} {$gpFormEnd} {* REQUIRED *}
				
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
