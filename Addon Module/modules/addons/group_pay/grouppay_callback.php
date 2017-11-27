<?php

include '../../../init.php';
include '../../../includes/functions.php';

require_once 'functions.php';

//Check that the ipn is valid
$ipn = gp_ValidateIpn();

//$ipn = [
//    'status'    => true,
//    'data'      => [
//        'txn_id'        => 'text_trn_id_'.date('Ymdhis'), // Randon Txn Id
//        'payer_email'   => 'test@test.com.au',                  // Email
//        'custom'        => '_REPLACE_WITH_VALID_HASH',          // Hash of the user
//        'mc_gross'      => 10,                                  // Amount of payment (fixed @ $10)
//        'mc_fee'        => .10,                                 // Amount of fee (Fixed at $10c)
//        'payment_status'=> 'Completed',                         // Payment status
//    ],
//];

// Invalid
if($ipn['status'] === false){
    gp_LogGatewayTrans('PayPal', $ipn['data'], 'Invalid IPN');
    exit;
}

// Check that the payment status is completed
if($ipn['data']['payment_status'] !== 'Completed') {
    gp_LogGatewayTrans('PayPal', $ipn['data'], $ipn['data']['payment_status']);
    exit;
}


if (gp_CountTransactionById($ipn['data']['txn_id']) > 0) {
    exit;
}

$gpSettings = gp_LoadSettings();

// Begin to credit the client
$user = gp_LoadUserFromHash($ipn['data']['custom']);
gp_LogGatewayTrans('PayPal', $ipn['data'], 'Successful');

if ($user !== null) {
    // Apply the Credit
    // Get the clients currency and apply the rate
    $currency = getCurrency($user->id);



    // Write the Transaction
    gp_insertTransaction($user->id, $ipn['data'], $gpSettings, $currency['rate']);

    // Add to credit balance
    gp_AddToCreditBalance($user->id, $ipn['data']['mc_gross']);

    // Add it to the credit log
    gp_LogCredit($user->id, $gpSettings, $ipn['data']['payer_email'], $ipn['data']['mc_gross']);

    run_hook('groupPay_paymentComplete', [
        'clientId'  => $user->id,
        'paypalInfo'=> $ipn['data'],
    ]);

}
