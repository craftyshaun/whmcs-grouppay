<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

define('CLIENTAREA', true);

require __DIR__ . '/init.php';
require __DIR__ . '/modules/addons/group_pay/functions.php';

$ca = new ClientArea();

$settings = gp_LoadSettings();

$ca->setPageTitle($settings['SystemName']);

$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('grouppay.php', $settings['SystemName']);

$ca->initPage();

$userHash 	= $_GET['hash'];
$fromPaypal = $_GET['fromPaypal'] === 'true';

if ($ca->isLoggedIn()) {
	$loggedInHash = gp_HashUserId($ca->getUserID());
	$ca->assign('loggedInHash', $loggedInHash);
}

if (isset($userHash) && (!empty($userHash) && $userHash !== $loggedInHash)) {
    $user = gp_LoadUserFromHash($userHash);
    $ca->assign('clientInfo', $user);
    $ca->assign('clientHash', $userHash);

    $userId            = $user->id;
    $anotherClientHash = true;
    $clientFound       = !empty($user);
} else {
	$userId            = $ca->getUserId();
	$anotherClientHash = false;
	$clientFound       = false;
}

$ca->assign('anotherClientHash', $anotherClientHash);
$ca->assign('clientFound', $clientFound);

if (!$anotherClientHash || $settings['hidePublicPayments'] !== 'on') {
    $ca->assign('pastPayments', gp_LoadPreviousPayments($userId, $settings['SystemName']));
}

$ca->assign('grouppayActive', $settings['Enabled'] === 'on');
$ca->assign('hidePublicPayments', $settings['HidePublicPayments'] === 'on');
$ca->assign('SystemName', $settings['SystemName']);
$ca->assign('fromPayPal', $fromPaypal);
$ca->assign('minPayment', $settings['MinPayment']);
$ca->assign('payPalUrl', 'https://www.paypal.com/cgi-bin/webscr');
//$ca->assign('payPalUrl', 'https://www.sandbox.paypal.com/cgi-bin/webscr');
$ca->assign('gpPayPalEmail', gp_LoadPayPalEmail());
$ca->assign('currency', getCurrency($userId));
$ca->assign('invoiceAmountDue', gp_loadInvoiceTotalDue($userId));

$ca->setTemplate('grouppay');

$ca->output();
