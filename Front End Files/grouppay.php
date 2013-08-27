<?php
//error_reporting(E_ALL);
global $CONFIG;

define("CLIENTAREA",true);
require("dbconnect.php");
require("includes/functions.php");
require("includes/clientareafunctions.php");

require("modules/addons/group_pay/functions.php");

//Load the gp Config

//Template Setup
$paypal_url = "https://www.paypal.com/cgi-bin/webscr";
//$paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";

$gpSettings 	= gp_LoadSettings();
$pagetitle = $gpSettings['SystemName'];
$pageicon = $gpSettings['PageIcon'];
$breadcrumbnav = '<a href="index.php">'.$_LANG['globalsystemname'].'</a> > <a href="grouppay.php">'.$gpSettings['SystemName'].'</a>';
 
initialiseClientArea($pagetitle,$pageicon,$breadcrumbnav);

//require("init.php"); 

# Define the template filename to be used without the .tpl extension
$templatefile = "grouppay"; 
$userHash = $_GET['hash'];
$fromPaypal = $_GET['fromPaypal'] == 'true';



//Load the grouppay settings
$gpPayPalEmail 	= gp_LoadPayPayEmail();


//Define up some smarty vars for the template
$loggedInClientHash = $_SESSION['uid'] ? gp_HashUserId($_SESSION['uid']) : "";
$smartyvalues["loggedInClientHash"] = $loggedInClientHash;



//If we are loading this for a user (other than logged in if applicable)
if(isset($userHash) && ($userHash != "" && $userHash != $loggedInClientHash)){
	$smartyvalues["anotherClientHash"] = true;
	$clientId = gp_LoadUserFromHash($userHash);
	$result = mysql_query("SELECT * from tblclients where id = $clientId");


	$smartyvalues["clientFound"] = ($clientInfo = mysql_fetch_assoc($result));
	$smartyvalues["clientInfo"] = $clientInfo;
	$smartyvalues["clientHash"] = $userHash;
}else{
	$smartyvalues["anotherClientHash"] = false;
	// Load the past payments
	$clientId   = $_SESSION['uid'];
	$smartyvalues["clientFound"] = false;
}


// Load the past Payments
$pastPayments = array();

if(!$smartyvalues['anotherClientHash'] || $gpSettings['HidePublicPayments'] != "on"){
	$dbPastPayments = mysql_query("SELECT * from `tblcredit` where `clientid` = ".$clientId  ." and `description` LIKE '{$gpSettings['SystemName']}%'"); 
	while($pastPayment = mysql_fetch_array($dbPastPayments)){
		$newPayment 				= array();
		$newPayment['date'] 		= fromMySQLDate($pastPayment['date']);
		$newPayment['description'] 	= substr($pastPayment['description'],strlen($gpSettings['SystemName'])+7);
		$newPayment['amount'] 		= formatCurrency($pastPayment['amount']);

		$pastPayments[] 			= $newPayment;	
	}
}

$smartyvalues['pastPayments'] = $pastPayments;
$smartyvalues["grouppayActive"] = ($gpSettings['Enabled'] == "on"); 
$smartyvalues["hidePublicPayments"] = ($gpSettings['HidePublicPayments'] == "on");
$smartyvalues["SystemName"] = $gpSettings['SystemName'];
$smartyvalues["fromPaypal"] = $fromPaypal;	
$smartyvalues["minPayment"] = $gpSettings['MinPayment'];

//New in 1.07
$smartyvalues["hashLink"] = $CONFIG['SystemURL'].(substr($CONFIG['SystemURL'],-1) =="/" ? "" : "/")."grouppay.php?hash=".($_SESSION['uid'] ? gp_HashUserId($_SESSION['uid']) : "");
$currency = getCurrency($clientId);

//Invoice Total
$amtDue = 0;
$dbAmtDue = full_query("SELECT SUM(`total`) FROM `tblinvoices` WHERE `userid` = '$clientId' AND `status` = 'Unpaid'");
if($dbAmtDue){
	$amtDueRow = mysql_fetch_array($dbAmtDue);
	$amtDue = isset($amtDueRow[0]) ? $amtDueRow[0] : 0 ;
}
$smartyvalues["invAmountDue"] = $amtDue;




$smartyvalues["verifyAmtScript"] = "
<!-- Group Pay ".$gpSettings['Version']." -->
<script>
function checkAmt(limit){
	if(!(/[0-9]*\.?[0-9]+/.test(document.getElementsByName('amount')[0].value))){
		alert('Please Enter Valid Amount');
		return false;
	}else{
		if(parseFloat(document.getElementsByName('amount')[0].value) < parseFloat(limit)){
			alert('Please enter an amount higher than the minimum payment of \$'+limit);
			return false;
		}
	}
}
</script>";



//Start the form
$smartyvalues["gpFormStart"] = 
"
<!-- Begin Paypal Form -->
<form id=\"paypalForm\" action=\"".$paypal_url."\" onsubmit=\"return checkAmt(".$gpSettings['MinPayment'].")\" method=\"post\">
<input type=\"hidden\" name=\"cmd\" value=\"_xclick\">
<input type=\"hidden\" name=\"custom\" value=\"$userHash\">
<input type=\"hidden\" name=\"no_note\" value=\"1\">
<input type=\"hidden\" name=\"item_name\" value=\"".$CONFIG['CompanyName']." - ".$gpSettings['SystemName']." - ".$clientInfo['firstname']." ".$clientInfo['lastname'].($clientInfo['company'] ? "(".$clientInfo['company'].")":"")."\">
<input type=\"hidden\" name=\"currency_code\" value=\"".$currency['code']."\">
<input type=\"hidden\" name=\"return\" value=\"".$CONFIG['SystemURL'].(substr($CONFIG['SystemURL'],-1) =="/" ? "" : "/")."grouppay.php?fromPaypal=true\">
<input type=\"hidden\" name=\"cancel_return\" value=\"".$CONFIG['SystemURL'].(substr($CONFIG['SystemURL'],-1) =="/" ? "" : "/")."grouppay.php?hash=$userHash\">
<input type=\"hidden\" name=\"notify_url\" value=\"".$CONFIG['SystemURL']."/modules/addons/group_pay/grouppay_callback.php\">
<input type=\"hidden\" name=\"no_shipping\" value=\"1\">
<input type=\"hidden\" name=\"business\" value=\"$gpPayPalEmail\">

";

//end the form
$smartyvalues["gpFormEnd"] = 
"
<input type=\"image\" class=\"gppaypalimage\" style=\"height:40px; width:145px; border:none; \" src=\"https://www.paypalobjects.com/en_US/i/btn/btn_xpressCheckout.gif\" border=\"0\" name=\"submit\" alt=\"Make payments with PayPal - it's fast, free and secure!\">
</form>";

//Spit it out
outputClientArea("grouppay");
?>
