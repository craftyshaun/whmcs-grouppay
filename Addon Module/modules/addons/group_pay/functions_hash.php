<?php 
/**
 * WHMCS Group Pay Module Hashing Functions
 * Created By: 
 * 	Kadeo Pty Ltd
 * 	http://www.kadeo.com.au
 * 
 * License:
 * 	Copyright Kadeo Pty Ltd
 *  All Rights Reserved
 *  
 * 
 * This file contains the hash functions that are used.
 * The provided hash functions will be fine for 99% of the users, these are exposed
 * so that people can implement their own if they wish to.
 * The current hash using md5 means that you are not able to reverse engineer the
 * user from the hash. By salting the ID with the email it also means that people
 * can't brute force your page just by hasing the values 1-x.
 * 
 * To get a users name you must know their email and their WHMCS id. 
 * 
 */


if(!function_exists("str_ireplace")){
 function str_Ireplace($needle, $replacement, $haystack) {
   $i = 0;
   while (($pos = strpos(strtolower($haystack),
     strtolower($needle), $i)) !== false) {
       $haystack = substr($haystack, 0, $pos) . $replacement
           . substr($haystack, $pos + strlen($needle));
       $i = $pos + strlen($replacement);
   }
   return $haystack;
 }
}

/**
 * Loads a userId From a provided hash string
 *
 * @param String $hash Hash String
 * @return UserId of the user that the hash relates too.
 */
function gp_LoadUserFromHash($hash) {
	//Kill the Dashes
	$hash = str_replace ( "-", "", $hash );
    $hash = mysql_escape_string($hash);
	$result = mysql_query ( "SELECT `id` from tblclients where md5(CONCAT(id,email)) = '$hash'" );
	if($result){
		$row = mysql_fetch_row ( $result );
		return $row [0];
	}else{
		return false;	
	}
}
/**
 * Hashes a user id.
 *
 * @param string $userId UserId to hash
 * @return string Hashed User Id
 */
function gp_HashUserId($userId) {
    $userId = mysql_escape_string($userId);
	$result = mysql_query ( "SELECT `email` from tblclients where id = $userId");
	$row = mysql_fetch_row ( $result );
	$email = $row [0];
	$md5 = md5 ( $userId.$email );
	return substr ( chunk_split ( $md5, 5, "-" ), 0, - 1 );
}
?>