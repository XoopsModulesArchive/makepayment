<?php
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP MakePayment System                            //
//                    Copyright (c) 2007 chronolabs.org.au                  //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //
// Author: Simon Roberts (AKA WISHCRAFT)                                     //
// Site: http://www.chronolabs.org.au                                        //
// Project: The Chrononaut Project                                           //
// ------------------------------------------------------------------------- //

require_once "admin_header.php";
error_reporting(E_ALL);
global $xoopsDB;

if (isset($_GET)) {
    foreach ($_GET as $k => $v) {
      $$k = $v;
    }
  }

  if (isset($_POST)) {
    foreach ($_POST as $k => $v) {
      $$k = $v;
    }
  }

	switch($op){
	case "dorefund":
		require_once dirname(__DIR__) . '/class/nusoap/nusoap.php';			
		$client = new soapclient('http://service.merchlogin.com/?wsdl', true,
				$proxyhost, $proxyport, $proxyusername, $proxypassword);
		
		$err = $client->getError();
		if ($err) {
			$xoopsTpl->assign("output1",'<h2>Constructor error</h2><pre>' . $err . '</pre>');
		}

		$param = array('merchantId'=>$xoopsModuleConfig['MerchantID'],
     				   'TransactionID'=>$TransactionID);
								
		$result = $client->call($func, $param, '', '', false, true);
		
		switch($result['Status']){
		case 0:
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Refund was declined - '.$result['Message']);
			break;
		case 1:
			$sql = "update ".$xoopsDB->prefix('ticket_transactions')." set TransMode = 'Refunded',  TransModeChanged = '".date('Y-m-d H:i:s')."' where ticket_id = '$ticket_id' and TransactionID = '$TransactionID'";
			$xoopsDB->query($sql); 
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Refund was Successful - '.$result['Message']);
			break;
		case 2:
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Error Occured - '.$result['Message']);
			break;
		case 3:
			$sql = "update ".$xoopsDB->prefix('ticket_transactions')." set TransMode = 'Refunded',  TransModeChanged = '".date('Y-m-d H:i:s')."' where ticket_id = '$ticket_id' and TransactionID = '$TransactionID'";
			$xoopsDB->query($sql);
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Pending - '.$result['Message']);
			break;		
		case 4:
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Refund was Scrubbed - '.$result['Message']);
			break;			
		case 5:
			redirect_header(XOOPS_URL.'/module/makepayment/admin/?op=refunds',0,'Refund didn\'t pass fraud test - '.$result['Message']);
			break;			
		}
		break;
	case "refunds":
	xoops_cp_header();
		?>
<form action="?op=transaction&subop=search" method="post" name="form1" target="_self" id="form1">
<table width="400" border="0" cellspacing="2">
  <tr>
    <th colspan="3" scope="row"><em>Search for Transaction</em></th>
  </tr>
  <tr>
    <th width="121" scope="row">Card Name:</th>
    <td width="10">&nbsp;</td>
    <td width="255"><label>
      <input name="CardName" type="text" id="CardName" size="35">
    </label></td>
  </tr>
  <tr>
    <th scope="row">Card CCV:</th>
    <td>&nbsp;</td>
    <td><input name="CardCCV" type="text" id="CardCCV" size="5" maxlength="3"></td>
  </tr>
  <tr>
    <th scope="row">SSN:</th>
    <td>&nbsp;</td>
    <td><input name="SSN" type="text" id="SSN" size="35"></td>
  </tr>
  <tr>
    <th scope="row">Transaction ID:</th>
    <td>&nbsp;</td>
    <td><input name="TransID" type="text" id="TransID" size="35"></td>
  </tr>
  <tr>
    <th colspan="3" scope="row">
      <label>
        <input type="submit" name="Search" id="Search" value="Submit">
        </label>
      </th>
  </tr>
</table></form>
<br>
<? 
	if ($subop=='search') {
		if (strlen($CardName)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("CC_Name Like '%s'",$CardName);
		}
		if (strlen($CardCCV)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("CC_CCV = '%s'",$CardCCV);
		}
		if (strlen($SSN)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("ECHECK_SSN = '%s'",$SSN);
		}
		if (strlen($TransID)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("TransactionID Like '%s'",$TransID);
		}
		if (strlen($where)>0){
			$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Refunded' and $where order by TransModeChanged desc limit 300";
		} else {
			$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Refunded'  order by TransModeChanged desc limit 300";
		}
	} else {
		$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Refunded' order by TransModeChanged desc limit 40";
	}
	$ret = $xoopsDB->query($sql);
	
?>
<table width="99%" border="0" cellspacing="1">
  <tr>
    <th width="20%" nowrap="nowrap" scope="row"><div align="center">Transaction ID</div></th>
    <td width="9%" nowrap="nowrap"><div align="center">Amount</div></td>
    <td width="8%" nowrap="nowrap"><div align="left">Currency</div></td>
    <td width="18%"><div align="left">Name</div></td>
    <td width="19%"><div align="left">Phone</div></td>
    <td width="12%"><div align="center">Internal Status</div></td>
    <td width="14%"><div align="center">Action</div></td>
  </tr>
<? while($row = $xoopsDB->fetchArray($ret)){ ?>
  <tr>
    <th nowrap="nowrap" scope="row"><div align="center"><? echo $row['TransactionID']; ?></div></th>
    <td nowrap="nowrap"><div align="center"><? echo $row['Amount']; ?></div></td>
    <td nowrap="nowrap"><div align="left"><? echo $row['Currency']; ?></div></td>
    <td><div align="left"><? echo $row['Firstname'].' '.$row['Lastname']; ?></div></td>
    <td><div align="left"><? echo $row['Address']; ?></div></td>
    <td><div align="center"><? echo $row['InternalStatus']; ?></div></td>
    <td><div align="center"><? switch($row['InternalStatus']){
	case "Successful":
		echo "<a href='?op=dorefund&ticket_id=".$row['ticket_id']."&TransactionID=".$row['TransactionID']."'>Refund</a>";
		break;
	case "Pending":
		echo "<a href='?op=dorefund&ticket_id=".$row['ticket_id']."&TransactionID=".$row['TransactionID']."'>Refund</a>";	
		break;
	} ?></div></td>
  </tr>
<? } ?>
</table>

<?

		break;
	default:
	xoops_cp_header();
		?>
<form action="?op=transaction&subop=search" method="post" name="form1" target="_self" id="form1">
<table width="400" border="0" cellspacing="2">
  <tr>
    <th colspan="3" scope="row"><em>Search for Transaction</em></th>
  </tr>
  <tr>
    <th width="121" scope="row">Card Name:</th>
    <td width="10">&nbsp;</td>
    <td width="255"><label>
      <input name="CardName" type="text" id="CardName" size="35">
    </label></td>
  </tr>
  <tr>
    <th scope="row">Card CCV:</th>
    <td>&nbsp;</td>
    <td><input name="CardCCV" type="text" id="CardCCV" size="5" maxlength="3"></td>
  </tr>
  <tr>
    <th scope="row">SSN:</th>
    <td>&nbsp;</td>
    <td><input name="SSN" type="text" id="SSN" size="35"></td>
  </tr>
  <tr>
    <th scope="row">Transaction ID:</th>
    <td>&nbsp;</td>
    <td><input name="TransID" type="text" id="TransID" size="35"></td>
  </tr>
  <tr>
    <th colspan="3" scope="row">
      <label>
        <input type="submit" name="Search" id="Search" value="Submit">
        </label>
      </th>
  </tr>
</table></form>
<br>
<? 
	if ($subop=='search') {
		if (strlen($CardName)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("CC_Name Like '%s'",$CardName);
		}
		if (strlen($CardCCV)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("CC_CCV = '%s'",$CardCCV);
		}
		if (strlen($SSN)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("ECHECK_SSN = '%s'",$SSN);
		}
		if (strlen($TransID)>0){
			if (strlen($where)>0) { $where .= ' and '; }
			$where .= sprintf("TransactionID Like '%s'",$TransID);
		}
		if (strlen($where)>0){
			$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Open' and $where order by DateCreated desc limit 300";
		} else {
			$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Open'  order by DateCreated desc limit 300";
		}
	} else {
		$sql = "select * from ".$xoopsDB->prefix('ticket_transactions')." where TransMode = 'Open' order by DateCreated desc limit 40";
	}
	$ret = $xoopsDB->query($sql);
	
?>
<table width="99%" border="0" cellspacing="1">
  <tr>
    <th width="20%" nowrap="nowrap" scope="row"><div align="center">Transaction ID</div></th>
    <td width="9%" nowrap="nowrap"><div align="center">Amount</div></td>
    <td width="8%" nowrap="nowrap"><div align="left">Currency</div></td>
    <td width="18%"><div align="left">Name</div></td>
    <td width="19%"><div align="left">Phone</div></td>
    <td width="12%"><div align="center">Internal Status</div></td>
    <td width="14%"><div align="center">Action</div></td>
  </tr>
<? while($row = $xoopsDB->fetchArray($ret)){ ?>
  <tr>
    <th nowrap="nowrap" scope="row"><div align="center"><? echo $row['TransactionID']; ?></div></th>
    <td nowrap="nowrap"><div align="center"><? echo $row['Amount']; ?></div></td>
    <td nowrap="nowrap"><div align="left"><? echo $row['Currency']; ?></div></td>
    <td><div align="left"><? echo $row['Firstname'].' '.$row['Lastname']; ?></div></td>
    <td><div align="left"><? echo $row['Address']; ?></div></td>
    <td><div align="center"><? echo $row['InternalStatus']; ?></div></td>
    <td><div align="center"><? switch($row['InternalStatus']){
	case "Successful":
		echo "<a href='?op=dorefund&ticket_id=".$row['ticket_id']."&TransactionID=".$row['TransactionID']."'>Refund</a>";
		break;
	case "Pending":
		echo "<a href='?op=dorefund&ticket_id=".$row['ticket_id']."&TransactionID=".$row['TransactionID']."'>Refund</a>";	
		break;
	} ?></div></td>
  </tr>
<? } ?>
</table>

<?

		break;
	}
xoops_cp_footer();

?>
