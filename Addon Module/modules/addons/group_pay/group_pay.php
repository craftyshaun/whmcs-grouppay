<?php
/*
 * Admin mod for Group Pay
 * Author: Shaun Deans - Kadeo Pty Ltd
 * WWW: http://www.kadeo.com.au
 */

require_once 'functions.php';

//
function group_pay_config() {

	$configarray = array('name' => 'Group Pay',
	'version' => '1.5',
	'author' => 'Kadeo',
	'description' => 'Allows the ability for your clients to provide payment links which allow non registered clients to submit payments which are added as credit to your clients accounts.',
	'fields' => array(
		'Enabled' => array ("FriendlyName" => "Enabled", "Type" => "yesno", "Size" => "25",),
		'SystemName' => array("FriendlyName" => "System Name", "Type" => "text", "Size"=>"20"),
		'LicenseKey' => array("FriendlyName" => "LicenseKey", "Type" => "text", "Size"=>"10",),
		'MinPayment' => array("FriendlyName" => "Minimum Payment ($)", "Type" => "text", "Size"=>"10"),
		'PageIcon' => array("FriendlyName" => "Page Icon", "Type" => "text", "Size"=>"30"),
		)
	);

	return $configarray;
}

function group_pay_activate() {
	
	$orig_settings = array ();
	$settings = array();
	$result = mysql_query ( "SELECT setting, value FROM `tblconfiguration` WHERE setting like 'GroupPay.%'" );
	while ( $row = mysql_fetch_assoc ( $result ) )
		$orig_settings [str_ireplace ( "GroupPay.", "", $row ['setting'] )] = $row ['value'];
	
	if ($orig_settings) {
		//Copy original settings to new format
		$settings['SystemName'] = $orig_settings['SystemName'];
		$settings['MinPayment'] = $orig_settings['MinPayment'];
		$settings['LicenseKey'] = $orig_settings['LicenseKey'];
		$settings['PageIcon'] = $orig_settings['PageIcon'];
		$settings['LocalKey'] = $orig_settings['LocalKey'];
		if ($settings['Enabled'] == "1") {
			$settings['Enabled'] = "on";
		} else {
			$settings['Enabled'] = "off";
		}
		$settings ['access'] = "1";

		// Clean the old tblconfiguration of any GroupPay mess
		mysql_query("delete from `tblconfiguration` where setting like setting like 'GroupPay.%'");

	} else {
		//Enabled defaults
		$settings ['SystemName'] = "Group Pay";
		$settings ['MinPayment'] = "10";
		$settings ['LocalKey'] = "";
		$settings ['access'] = "1";
	}

	// Save the setting into the addon configuration table
	foreach ( $settings as $setting => $value ) {
		mysql_query("INSERT INTO tbladdonmodules VALUES('group_pay', '".$setting."', '". $value . "')");
	}

	
}

/**
 * Remove the GroupPay configuration from the database.
 **/
function group_pay_deactivate() {
	mysql_query("DELETE FROM tbladdonmodules WHERE module = 'group_pay'");
}

/**
 * Main Output function for the admin area
 **/
function group_pay_output($vars) {
	//Check for paypal

	if(!gp_CheckForPaypal()){
		echo "<p><strong>No Paypal Account</strong></p>
			<p>Group Pay has been configured to use a paypal account.</p>
			<p>Please add a paypal gateway.</p>";
	}else{
		//Do we have to save the settings
		$message = "";
				
		$validLic = gp_ValidLicense();
		
		//Load Current Settins
		$settings = gp_LoadSettings();
		$paypalEmail = gp_LoadPayPayEmail();
		
		// Run a legacy file check
		group_pay_legacy_file_check();

		echo $message != "" ? "<p><b>$message</b></p>" : "" ;
		echo '<table><tr><td>Enabled:</td><td>'.($settings['Enabled'] == "on" && $validLic[0] ? '<span style="color: green; font-weight: bold;">Enabled</span>' : '<span style="color: red; font-weight: bold;">Disabled</span>').'</td></tr>';
		echo '<tr><td>License Key:</td><td>'.$settings['LicenseKey'].'</td></tr>';
		if($validLic[0]){
			echo '<tr><td>System Name:</td><td>'.$settings['SystemName'].'</td></tr>';
			echo "<tr><td>PayPal Account:</td><td>$paypalEmail&nbsp;(<i>Loaded from PayPal gateway.</i>)</td></tr>";
			echo '<tr><td>Min Deposit:</td><td>$ '.formatcurrency($settings['MinPayment']).'</td></tr>';
			echo '<tr><td>Page Icon:</td><td>'.($settings['PageIcon']==""?"<i>None Configured</i>":$settings['PageIcon']).'</td></tr>';
		}else {
			echo '<tr valign="top"><td style="color:red">License Error:</td><td>'.$validLic[1].'</tr>';
		}
		echo '<tr valign="top"><td>Current Version:</td><td>'.$settings['version'].'</tr>';
		echo '<tr><td>&nbsp;</td><td>&nbsp;</tr>';
		echo '<tr><td>Settings may be altered in Setup->Addon Modules->Group Pay</td><td>&nbsp;</tr>';
		echo '<tr><td>&nbsp;</td><td>&nbsp;</tr></table>';
		
		echo "<h3>We would like your support!</h3><p>If you like <a target='_blank' href='http://www.whmcs.com/appstore/304/Group-Pay---Its-Clan-Pay-for-your-WHMCS.html'>GroupPay</a> please <b>let people know!</b>".
				"<ul>".
					"<li><a target='_blank' href='http://www.whmcs.com/appstore/304/Group-Pay---Its-Clan-Pay-for-your-WHMCS.html'>See GroupPay on WHMCS App Store</a></li>".
					"<li><a target='_blank' href='http://www.facebook.com/sharer.php?u=http://kadeo.com.au/'>Share Kadeo's Home Page on Facebook</a></li>".
					"<li><a target='_blank' href='http://www.facebook.com/sharer.php?u=http://kadeo.com.au/design-and-development/whmcs-dev/whmcs-modules/72-group-pay.html'>Share Kadeo's Group Pay Page on Facebook</a></li>".
					"<li><a target='_blank' href='http://twitter.com/share?url=http://kadeo.com.au/&text=I%27m+using+Group+Pay+for+WHMCS+by+%40craftingtheweb'>Tweet About Group Pay</a></li>".
				"</ul>".
				
			"Thanks Heaps!</p>";
	}
}

/**
 * Shown in the sidebar when the module is active
 **/
function group_pay_sidebar($vars) {
	$modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $licenceKey = $vars['LicenseKey'];
    $systemName = $vars['SystemName'];

    $sidebar = '<span class="header"><img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" />'.$systemName.'</span>

    <p><strong>Licence Key:</strong> '.$licenceKey.'</p>';

    return $sidebar;
}

/**
 * Update Function (Not Required Yet)
 **/
function group_pay_update($vars) {
	/*$currentVersion = $vars['version'];

	if (floatval($currentVersion) < 1.2) {
		//Insert any necessary update code here.
	}*/
}


/**
 * Check for old files present
 *  - Display an error if they are present
 **/
function group_pay_legacy_file_check(){
	
	$legacyFiles = array(
		"/modules/admin/group_pay/functions.php",
		"/modules/admin/group_pay/functions_hash.php",
		"/modules/admin/group_pay/group_pay.php",
		"/modules/admin/group_pay/grouppay_callback.php"
	);

	$legacyFilesFound = array();

	// For each of the above legacy files 
	//  Check if the file exists
	//    if it does add to array
	foreach ( $legacyFiles as $legacyFile ) {
	
	  if ( file_exists(ROOTDIR.$legacyFile) ) {
		$legacyFilesFound[] = $legacyFile;
	  }
	}

	// If we have found some display error!
	if(count($legacyFilesFound) > 0){
		echo '<div class="errorbox">';
		echo	'<strong>Legacy Files Detected</strong><br/>';
		echo	'The following legacy files have been detected:';
		echo 	'<ul>';
		foreach ($legacyFilesFound as $legacyFile) {
			echo '<li>'.$legacyFile.'</li>';
		}
		echo	'</ul>';
		echo	'These legacy files have been replaced by the new module files and should be removed as they are no longer required.';
		echo	'</div>';

	}



}

?>
