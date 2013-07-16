<?php
require_once 'functions_hash.php';

function gp_LoadSettings() {
		
	$settings = array ();
	$result = mysql_query ( "SELECT setting, value FROM `tbladdonmodules` WHERE module = 'group_pay'" );
	while ( $row = mysql_fetch_assoc ( $result ) )
		$settings [$row ['setting']] = $row ['value'];
	
	return $settings;

    /*
        $settings['enabled'] = '1';
    */
}

/**
 * Save the local key to the database
 **/
function gp_SaveLocalKey($keyVal){
        mysql_query ( "update `tbladdonmodules` set value = '" . $keyVal . "' where module = 'group_pay' AND setting = 'LocalKey'" );
}

function gp_CheckForPaypal() {
	$result = mysql_query ( "SELECT count(*) as theCount from tblpaymentgateways where gateway = 'paypal'" );
	$row = mysql_fetch_assoc ( $result );
	return $row ['theCount'] > 0;
}

function gp_LoadPayPayEmail() {
	$result = mysql_query ( "SELECT `value` from tblpaymentgateways where gateway = 'paypal' and setting = 'email'" );
	$row = mysql_fetch_row ( $result );

	// Explode out the emails incase they are using multiple emails
	$paypalEmails = explode(",",$row [0]);

	return $paypalEmails[0];

}

function gp_ValidateIpn() {
	// parse the paypal URL\
	$paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
	//$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
	

	$ipn_data = array ();
	$ipn_response = "";
	
	$url_parsed = parse_url ( $paypal_url );
	
	// generate the post string from the _POST vars aswell as load the
	// _POST vars into an arry so we can play with them from the calling
	// script.
	$post_string = '';
	foreach ( $_POST as $field => $value ) {
		$ipn_data ["$field"] = $value;
		$post_string .= $field . '=' . urlencode ( stripslashes ( $value ) ) . '&';
	}
	$post_string .= "cmd=_notify-validate"; // append ipn command

	// open the connection to paypal
	$fp = fsockopen ( $url_parsed ['host'], "80", $err_num, $err_str, 30 );
	if (! $fp) {
		return array (false, "Couldn't Open Host" );
	
	} else {
		// Post the data back to paypal
		fputs ( $fp, "POST $url_parsed[path] HTTP/1.1\r\n" );
		fputs ( $fp, "Host: $url_parsed[host]\r\n" );
		fputs ( $fp, "Content-type: application/x-www-form-urlencoded\r\n" );
		fputs ( $fp, "Content-length: " . strlen ( $post_string ) . "\r\n" );
		fputs ( $fp, "Connection: close\r\n\r\n" );
		fputs ( $fp, $post_string . "\r\n\r\n" );
		
		// loop through the response from the server and append to variable
		while ( ! feof ( $fp ) ) {
			$ipn_response .= fgets ( $fp, 1024 );
		}
		
		fclose ( $fp ); // close connection
	

	}
	
	/*Second logical for sandbox account due to IPN not sending verified flag.
	HTTP/1.0 302 Found
	Location: https://www.sandbox.paypal.com
	Server: BigIP
	Connection: close
	Content-Length: 0
	*/
	if (eregi ( "VERIFIED", $ipn_response ) || $paypal_url == "https://www.sandbox.paypal.com/cgi-bin/webscr") {
		
		return array (true, $ipn_data );
	
	} else {
		return array (false, "Not Verified-".$ipn_response." ".$post_string );
	
	}
}

function gp_LogGatewayTrans($gateway, $data, $result) {
	if (is_array ( $data ))
		$data = print_r ( $data, true );
	
	$result = mysql_query ( 'INSERT INTO `tblgatewaylog` (date,gateway,data,result) VALUES (now(),\'' . $gateway . '\',\'' . mysql_real_escape_string ( $data ) . ('' . '\',\'' . $result . '\')') );
}



?>