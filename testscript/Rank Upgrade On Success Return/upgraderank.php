<?

include "mainfile.php";

if ($_GET['trigger_pass'] == md5($_GET['TransactionID'].$_GET['custom'].$_GET['payment'])&&strlen($_GET['trigger_pass'])==32) {
	
	if (isset($_GET)) {
		foreach ( $_GET as $k => $v ) {
			${$k} = $v;
		}
	}
	
	$uid = $_GET['uid'];
	$payment = $_GET['payment'];
	
} else {

	if (isset($_POST)) {
		foreach ( $_POST as $k => $v ) {
			${$k} = $v;
		}
	}
	
	$ad_id = $_GET['ad_id'];
	$payment = 'default';
}

switch ($payment){
case "cancel":
	redirect_header("index.php",2,"<br>Payment Cancelled!<br>Deleting Advertisement");
	break;
case "return":

	global $xoopsDB;	
	$query_rsTitle = "update _ete_users set rank='9' where uid = ".$uid;
	$rsTitle = $xoopsDB->execute($query_rsTitle);

	redirect_header("index.php",2,"<br>Donation Accepted!<br>Redirecting to main Page");
	break;
default:
	header('Location: http://www.extraterrestrialembassy.com/');
}
?>
