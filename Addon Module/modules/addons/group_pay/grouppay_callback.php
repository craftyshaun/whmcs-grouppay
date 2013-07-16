<?php
	
	include '../../../dbconnect.php';
	include '../../../includes/functions.php';
	
	require_once 'functions.php';
		
	//Check that the ipn is valid
	$ipnValid = gp_ValidateIpn();
	
	/*$ipnValid = array (
	            true, 
	            array(
	                "txn_id" => "text_trn_id_".date("Ymdhis"),      // Randon Txn Id
	                "payer_email" => "test@test.com.au",
	                "custom" => "_REPLACE_WITH_VALID_HASH",         // Hash of the user
	                "mc_gross" => 10,                               // Amount of payment (fixed @ $10)
	                "mc_fee" => .10                                 // Amount of fee (Fixed at $10c)
	            ) 
	    );*/
	
	if(!$ipnValid[0]){
		//Its Invalid
		gp_LogGatewayTrans("PayPal",$ipnValid[1],"Invalid IPN");
		exit();
	}
	
    // Check that the payment status is completed
    if($ipnValid[1]['payment_status'] !=="Completed"){
        gp_LogGatewayTrans("PayPal",$ipnValid[1],$ipnValid[1]['payment_status']);
        exit();
    }


	//Check that we havn't credited for it already
  	$query = 'SELECT * FROM tblaccounts WHERE transid=\'' . $ipnValid[1]['txn_id'] . '\'';
        if(mysql_num_rows(mysql_query ($query))){
          exit ();
        }
        $gpSettings = gp_LoadSettings();
    
        //Begin to credit the client
   	//TODO: Uncomment
    //$clientId = 1;
    $clientId = gp_LoadUserFromHash($ipnValid[1]['custom']);
   	gp_LogGatewayTrans("PayPal",$ipnValid[1],"Successful");  
   	
   	if($clientId){
   		//Apply the Credit
		// Get the clients currecny and apply the rate
		$currency = getCurrency($clientId);
				
   		//Write the Transactions
   		mysql_query ("INSERT INTO `tblaccounts` (userid,gateway,`date`,description,amountin,fees,transid,invoiceid,`rate`)
   						 values ($clientId,'paypal',now(),'".$gpSettings['SystemName']." Credit',".$ipnValid[1]['mc_gross'].",".
   					 			$ipnValid[1]['mc_fee'].",'".$ipnValid[1]['txn_id']."',0,'".$currency['rate']."')");
								
		//Increase the credit balance
   		mysql_query ("UPDATE `tblclients` set credit = credit + ".$ipnValid[1]['mc_gross']." where id = $clientId");

   		//Add it to the credit log
   		mysql_query ("INSERT INTO `tblcredit` (clientid,date,description,amount)
					 values ($clientId,now(),'".$gpSettings['SystemName']." Credit ".$ipnValid[1]['payer_email']."',".$ipnValid[1]['mc_gross'].")");
		
		run_hook("groupPay_paymentComplete",array("clientId"=>$clientId, "paypalInfo"=> $ipnValid[1]));	
   	
   	}
    
   
?>