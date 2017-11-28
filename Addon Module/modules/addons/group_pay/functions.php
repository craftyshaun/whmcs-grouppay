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
	$paypalUrl = 'https://ipnpb.paypal.com/cgi-bin/webscr';
//	$paypalUrl = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';

	$ipnData = 'cmd=_notify-validate';

	foreach ($_POST as $field => $value) {
		$ipnData .= '&' . $field . '=' . rawurlencode($value);
	}

	$ch = curl_init($paypalUrl);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $ipnData);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);

    $result = curl_exec($ch);

    if (!$result) {
        curl_close($ch);

        return [
		    'status'    => false,
            'data'      => 'cURL error: [' . curl_errno($ch) . '] ' . curl_error($ch),
        ];
    }


    $info = curl_getinfo($ch);
    $httpCode = $info['http_code'];

    if ($httpCode !== 200) {
        curl_close($ch);

        return [
            'status'    => false,
            'data'      => 'PayPal responded with http code ' . $httpCode,
        ];
    }

    curl_close($ch);

	if ($result === 'VERIFIED') {
	    return [
		    'status'    => true,
            'data'      => $_POST,
        ];
    }

	return [
        'status'    => false,
        'data'      => "Not Verified - {$result} - {$ipnData}",
    ];
}

/**
 * Log transaction from gateway
 *
 * @param $gateway
 * @param $data
 * @param $result
 */
function gp_LogGatewayTrans($gateway, $data, $result) {
	if (is_array($data)) {
        $data = print_r($data, true);
    }

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
        'description'   => "{$settings['SystemName']} Credit ({$data['payer_email']})",
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
