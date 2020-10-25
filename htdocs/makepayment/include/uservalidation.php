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

function validateuser($uid){
	global $xoopsDB, $XoopsModuleConfig;
	$sql = "select uid, name, uname, email from ".$xoopsDB->prefix('users'). " where email = '$uid' or uname = '$uid' or uid = '$uid'";
	//echo $sql;
	$rows = $xoopsDB->query($sql);
	list($uid, $name, $uname, $email) = $xoopsDB->fetchRow($rows);
	
	if (isset($uid)&&isset($name)&&isset($uname)&&isset($email)){
		$ret['uid'] = $uid;
		$ret['name'] = $name;
		$ret['uname'] = $uname;
		$ret['email'] = $email;
		$ret['intabill_merchantid'] = $XoopsModuleConfig['MerchantID'];
	} else {
		$ret['uid'] = 0;
		$ret['name'] = 'Guest';
		$ret['uname'] = '';
		$ret['email'] = '';
		$ret['intabill_merchantid'] = $XoopsModuleConfig['MerchantID'];
	}
	return $ret;
}


function uservar($uid, $field, $source){
	global $xoopsDB;
	
	switch($source){
	default:
		$sql = "select $field from ".$xoopsDB->prefix('users')." where uid='$uid'";
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
?>