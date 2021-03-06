<?
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

function generate_ticketcrc($ticket_id){

	return md5(time().uniqid(rand()));

}

function validateticket($ticket_crc, $user){
	global $xoopsDB;
	
	if ($user['uid']==0){
		$sql = "select ticket_id from ".$xoopsDB->prefix('tickets')." where ticket_crc='$ticket_crc' and (ticket_sessionid = '".session_id()."')";
	} else {	
		$sql = "select ticket_id from ".$xoopsDB->prefix('tickets')." where ticket_crc='$ticket_crc' and (ticket_to_uid = '".$user['uid']."' or ticket_from_uid = '".$user['uid']."')";
	}
	$ret = $xoopsDB->query($sql);
	list($ticket_id) = $xoopsDB->fetchRow($ret);
	if (isset($ticket_id)){
		return $ticket_id;
	} else {
		return 0;
	}

}

function ticketvar($ticket_id, $field, $source){
	global $xoopsDB;
	
	switch($source){
	default:
		$sql = "select $field from ".$xoopsDB->prefix('tickets')." where ticket_id='$ticket_id'";
		break;
	case "crc":
		$sql = "select $field from ".$xoopsDB->prefix('tickets')." where ticket_crc='$ticket_id'";
		break;
	}
	$ret = $xoopsDB->query($sql);
	list(${$field}) = $xoopsDB->fetchRow($ret);
	if (isset(${$field})){
		return ${$field};
	} else {
		return '';
	}

}

function convert_currency($ttl,$cur,$primcur){

	return $ttl;

}

function getitemsonticket($ticket_id){
	global $xoopsDB;

	$sql = "select item_name, item_quanity, item_unitprice, item_currency, item_cateloguenum from ".$xoopsDB->prefix('tickets_items')." where ticket_id='$ticket_id'";
	$ret = $xoopsDB->query($sql,0,100);
	$i=0;
	while(list($item_name, $item_quanity, $item_unitprice, $item_currency, $item_cateloguenum) = $xoopsDB->fetchRow($ret)){
		$retn[$i]['name']=$item_name;
		$retn[$i]['quanity']=$item_quanity;
		$retn[$i]['unitprice']=sprintf("%01.2f", $item_unitprice);
		$retn[$i]['currency']=$item_currency;
		$retn[$i]['overall']=sprintf("%01.2f",convert_currency($retn[$i]['quanity']*$retn[$i]['unitprice'],$retn[$i]['currency'],'AUD'));
		$retn[$i]['cateloguenum']=$item_cateloguenum;
		$ttl_au=$ttl_au+$retn[$i]['overall'];
		$retn[$i]['overall'].=' AUD';
		$i++;
	}
	$sql = 'update '.$xoopsDB->prefix('tickets')." set ticket_totalvalue = '$ttl_au' where ticket_id = '$ticket_id'";
	$ret = $xoopsDB->query($sql);
	$retn[1]['total_aud']=sprintf("%01.2f", $ttl_au);
	return $retn;
		
}
?>