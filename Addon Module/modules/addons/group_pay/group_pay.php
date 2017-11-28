<?php

use WHMCS\Database\Capsule;

require_once 'functions.php';

/**
 * Configuration
 *
 * @return array
 */
function group_pay_config() {
	$configarray = array('name' => 'Group Pay',
	'version' => '1.7',
	'author' => 'Kadeo',
	'description' => 'Allows the ability for your clients to provide payment links which allow non registered clients to submit payments which are added as credit to your clients accounts.',
	'fields' => array(
		'Enabled' => array ("FriendlyName" => "Enabled", "Type" => "yesno", "Size" => "25",),
		'SystemName' => array("FriendlyName" => "System Name", "Type" => "text", "Size"=>"20"),
		'MinPayment' => array("FriendlyName" => "Minimum Payment", "Type" => "text", "Size"=>"10"),
		'PageIcon' => array("FriendlyName" => "Page Icon", "Type" => "text", "Size"=>"30"),
		'HidePublicPayments' => array ("FriendlyName" => "Disable Public List of Payments", "Type" => "yesno"),
		)
	);

	return $configarray;
}

/**
 * Activate module
 */
function group_pay_activate() {
	$orig_settings = [];
	$settings = [];

	$rows = Capsule::table('tblconfiguration')
					 ->select('setting', 'value')
					 ->where('setting', 'like', 'GroupPay.%')
					 ->get();

    foreach ($rows as $row) {
		$orig_settings[str_ireplace('GroupPay.', '', $row['setting'])] = $row['value'];
	}

	if (!empty($orig_settings)) {
    	// Copy original settings to new format
    	$settings['SystemName'] = $orig_settings['SystemName'];
		$settings['MinPayment'] = $orig_settings['MinPayment'];
		$settings['PageIcon'] = $orig_settings['PageIcon'];
		$settings['LocalKey'] = $orig_settings['LocalKey'];
		if ($settings['Enabled'] == "1") {
			$settings['Enabled'] = "on";
		} else {
			$settings['Enabled'] = "off";
		}

		$settings ['access'] = "1";

		Capsule::table('tblconfiguration')
			   ->where('setting', 'like', 'GroupPay.%')
			   ->delete();
	} else {
    	$settings['SystemName'] = "Group Pay";
		$settings['MinPayment'] = "10";
		$settings['LocalKey'] = "";
		$settings['access'] = "1";
	}

	$payload = [];

	// Save the setting into the addon configuration table
	foreach ($settings as $name => $value) {
		$payload[] = [
			'module' 	=> 'group_pay',
			'setting'	=> $name,
			'value'		=> $value,
		];
	}


	Capsule::table('tbladdonmodules')->insert($payload);
}

/**
 * Deactivate module
 */
function group_pay_deactivate() {
	Capsule::table('tblconfiguration')
		   ->where('module', 'group_pay')
		   ->delete();
}

/**
 * Admin area output
 */
function group_pay_output($vars) {
	$paypalEmail = gp_LoadPayPalEmail();

	// Check for paypal
	if($paypalEmail === false){
		echo "<p><strong>No Paypal Account</strong></p>
			<p>Group Pay has been configured to use a paypal account.</p>
			<p>Please add a paypal gateway.</p>";
	} else {
		// Do we have to save the settings
		$message = "";

		// Load Current Settins
		$settings = gp_LoadSettings();

		echo '        
        ' . (empty($message) ? '<p><b>' . $message . '</b></p>' : '') . '
		<table>
			<tr>
				<td>Enabled:</td>
				<td>' . ($settings['Enabled'] == "on" ? '<span style="color: green; font-weight: bold;">Enabled</span>' : '<span style="color: red; font-weight: bold;">Disabled</span>') . '</td>
			</tr>
			<tr>
				<td>System Name:</td><td>' . $settings['SystemName'] . '</td>
			</tr>
			<tr>
				<td>PayPal Account:</td>
				<td>' . $paypalEmail . ' (<i>Loaded from PayPal gateway.</i>)
				</td>
			</tr>
			<tr>
				<td>Min Deposit:</td>
				<td>' . formatCurrency($settings['MinPayment']) . '</td>
			</tr>
			<tr>
				<td>Page Icon:</td>
				<td>' . (empty($settings['PageIcon']) ? '<i>None Configured</i>' : $settings['PageIcon']) . '</td>
			</tr>
			<tr valign="top">
			    <td>Current Version:</td>
			    <td>'.$settings['version'].'
			</tr>
			<tr>
			    <td>&nbsp;</td>
			    <td>&nbsp;</td>
			</tr>
			<tr>
			    <td>Settings may be altered in Setup->Addon Modules->Group Pay</td>
			    <td>&nbsp;</td>
			</tr>
			<tr>
			    <td>&nbsp;</td>
			    <td>&nbsp;</td>
			</tr>
		</table>
        <h3>We would like your support!</h3><p>If you like <a target="_blank" href="http://www.whmcs.com/appstore/304/Group-Pay---Its-Clan-Pay-for-your-WHMCS.html">GroupPay</a> please <b>let people know!</b>
        <ul>
            <li><a target="_blank" href="http://www.whmcs.com/appstore/304/Group-Pay---Its-Clan-Pay-for-your-WHMCS.html">See GroupPay on WHMCS App Store</a></li>
            <li><a target="_blank" href="http://www.facebook.com/sharer.php?u=http://kadeo.com.au/">Share Kadeo\'s Home Page on Facebook</a></li>
            <li><a target="_blank" href="http://www.facebook.com/sharer.php?u=http://kadeo.com.au/design-and-development/whmcs-dev/whmcs-modules/72-group-pay.html">Share Kadeo\'s Group Pay Page on Facebook</a></li>
            <li><a target="_blank" href="http://twitter.com/share?url=http://kadeo.com.au/&text=I%27m+using+Group+Pay+for+WHMCS+by+%40craftingtheweb">Tweet About Group Pay</a></li>
        </ul>
        <p>Thanks Heaps!</p>';
	}
}

/**
 * Shown in the sidebar when the module is active
 */
function group_pay_sidebar($vars) {
    return <<<SIDEBAR
<span class="header">
	<img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" />{$vars['SystemName']}
</span>
SIDEBAR;
}

/**
 * Update Function (Not Required Yet)
 */
function group_pay_update($vars) {
	/*$currentVersion = $vars['version'];

	if (floatval($currentVersion) < 1.2) {
		//Insert any necessary update code here.
	}*/
}
