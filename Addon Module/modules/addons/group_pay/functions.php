<?php

use WHMCS\Database\Capsule;

/**
 * Load settings
 *
 * @return array
 */
function gp_LoadSettings() {
	$settings = [];

    $config = Capsule::table('tbladdonmodules')
					 ->where('module', 'group_pay')
					 ->get();

    foreach ($config as $item) {
		$settings[$item->setting] = $item->value;
	}

	return $settings;
}

/**
 * Load paypal email
 *
 * @return mixed
 */
function gp_LoadPayPalEmail() {
	$emails = Capsule::table('tblpaymentgateways')
					 ->select('value')
					 ->where('gateway', 'paypal')
					 ->where('setting', 'email')
					 ->first();

	return explode(',', $emails->value)[0];
}

/**
 * Load previous payments
 *
 * @param $user
 * @param $name
 * @return array
 */
function gp_LoadPreviousPayments($user, $name)
{
	$payments = Capsule::table('tblcredit')
				  	   ->where('clientId', $user)
				  	   ->where('description', 'like', $name . '%')
                       ->orderBy('date', 'desc')
                       ->get();

	$pastpmnt = [];

    foreach ($payments as $id => $payment) {
        $pastpmnt[$id] = [
            'date'          => fromMySQLDate($payment->date),
            'description'   => substr($payment->description, strlen($name) + 7),
            'amount'        => formatCurrency($payment->amount),
        ];
	}

	return $pastpmnt;
}

/**
 * Load total amount of invoices due
 *
 * @param $user
 * @return mixed
 */
function gp_loadInvoiceTotalDue($user)
{
    return Capsule::table('tblinvoices')
                  ->where('userid', $user)
                  ->where('status', 'Unpaid')
                  ->sum('total');
}

/**
 * Process IPN validation
 *
 * @return array
 */
function gp_ValidateIpn() {
//	$paypalUrl = 'https://www.paypal.com/cgi-bin/webscr';
	$paypalUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	$ipnData = [];
	$ipnResponse = '';

	$url = parse_url($paypalUrl);

	// generate the post string from the _POST vars aswell as load the
	// _POST vars into an array so we can play with them from the calling
	// script.
	$postData = [];

	foreach ($_POST as $field => $value) {
		$ipnData[$field] = $value;
	}

	$postData['cmd'] = '_notify-validate';

	// Open the connection to paypal
	$fp = fsockopen($url['host'], '443', $err_num, $err_str, 30);

	$postString = http_build_query($postData);

	if (!$fp) {
		return [
		    'status'    => false,
            'data'      => 'Could not open host',
        ];
	} else {
		// Post the data back to paypal
		fputs($fp, "POST {$url['path']} HTTP/1.1\r\n");
		fputs($fp, "Host: {$url['host']}\r\n");
		fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
		fputs($fp, "Content-length: " . strlen($postString) . "\r\n");
		fputs($fp, "Connection: close\r\n\r\n");
		fputs($fp, $postString . "\r\n\r\n");

		// loop through the response from the server and append to variable
		while (!feof($fp)) {
			$ipnResponse .= fgets($fp, 1024);
		}

		fclose ($fp);
	}

	/*Second logical for sandbox account due to IPN not sending verified flag.
	HTTP/1.0 302 Found
	Location: https://www.sandbox.paypal.com
	Server: BigIP
	Connection: close
	Content-Length: 0
	*/
	if (strstr($ipnResponse, "VERIFIED") || $paypalUrl === 'https://www.sandbox.paypal.com/cgi-bin/webscr') {
		return [
		    'status'    => true,
            'data'      => $ipnData,
        ];

	} else {
		return [
		    'status'    => false,
            'data'      => "Not Verified - {$ipnResponse} {$postString}",
        ];
	}
}

/**
 * Log transaction from gateway
 *
 * @param $gateway
 * @param $data
 * @param $result
 */
function gp_LogGatewayTrans($gateway, $data, $result) {
	if (is_array ( $data ))
		$data = print_r($data, true);

	Capsule::table('tblgatewaylog')->insert([
	    'date'      => Capsule::raw('NOW()'),
        'gateway'   => $gateway,
        'data'      => $data,
        'result'    => $result,
    ]);
}

/**
 * Loads a user From a provided hash string
 *
 * @param String $hash Hash String
 * @return mixed
 */
function gp_LoadUserFromHash($hash) {
	return Capsule::table('tblclients')
                  ->where(Capsule::raw('md5(CONCAT(id, email))'), str_replace ("-","", $hash))
                  ->first();
}

/**
 * Hashes a user id.
 *
 * @param int $userId UserId to hash
 * @return string Hashed User Id
 */
function gp_HashUserId($userId) {
    $email = Capsule::table('tblclients')
                    ->select('email')
                    ->where('id', $userId)
                    ->first();

    return !empty($email) ?
        substr(chunk_split(md5($userId.$email->email), 5, "-"), 0, -1) : '';
}


/**
 * Check if transaction exists
 *
 * @param $transaction
 * @return mixed
 */
function gp_CountTransactionById($transaction)
{
    return Capsule::table('tblaccounts')->where('transid', $transaction)->count();
}

/**
 * Insert transaction
 *
 * @param $userId
 * @param $data
 * @param $settings
 * @param $rate
 */
function gp_insertTransaction($userId, $data, $settings, $rate)
{
    Capsule::table('tblaccounts')->insert([
        'userid'        => $userId,
        'gateway'       => 'paypal',
        'date'          => Capsule::raw('NOW()'),
        'description'   => "{$settings['SystemName']} Credit",
        'amountin'      => $data['mc_gross'],
        'fees'          => $data['mc_fee'],
        'transid'       => $data['txn_id'],
        'invoiceid'     => 0,
        'rate'          => $rate,
    ]);
}

/**
 * Add credit to user
 *
 * @param $userId
 * @param $amount
 */
function gp_AddToCreditBalance($userId, $amount)
{
    Capsule::table('tblclients')
           ->where('id', $userId)
           ->increment('credit', $amount);
}

/**
 * Add to credit log
 *
 * @param $userId
 * @param $settings
 * @param $payerEmail
 * @param $gross
 */
function gp_LogCredit($userId, $settings, $payerEmail, $gross)
{
    Capsule::table('tblcredit')->insert([
        'clientid'      => $userId,
        'date'          => Capsule::raw('NOW()'),
        'description'   => "{$settings['SystemName']} Credit ({$payerEmail})",
        'amount'        => $gross,
    ]);
}
