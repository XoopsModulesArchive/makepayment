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
error_reporting(E_ALL);
$modversion['name'] = 'Send Money';
$modversion['version'] = 1.03;
$modversion['description'] = 'Sending Money Module for Xoops';
$modversion['author'] = "Simon Roberts";
$modversion['credits'] = "www.chronolabs.org.au";
$modversion['help'] = "simon@chronolabs.org.au";
$modversion['license'] = "GPL see LICENSE";
$modversion['official'] = 1;
$modversion['image'] = "images/makepayment_slogo.png";
$modversion['dirname'] = "makepayment";
$modversion['developer_lead'] = "Simon Roberts [wishcraft]";
$modversion['developer_contributor'] = "Just Me";
$modversion['developer_website_url'] = "http://www.paythem.biz";
$modversion['developer_website_name'] = "chronolabs.org.au";
$modversion['developer_email'] = "simon@chronolabs.org.au";

$modversion['sqlfile']['mysql'] = "sql/mysql.sql";
// Tables created by sql file (without prefix!)
$modversion['tables'][0] = "ticket_transactions";
$modversion['tables'][1] = "tickets";
$modversion['tables'][2] = "tickets_items";

// Admin things
$modversion['hasAdmin'] = 1;
$modversion['adminindex'] = "admin/index.php";
$modversion['adminmenu'] = "admin/menu.php";

$modversion['onUpdate'] = "include/update.php";

	if (!function_exists('NumToConfirm')){
	function NumToConfirm($uid){
		global $xoopsDB;
		$sql = 'select count(*) as rec_count from '.$xoopsDB->prefix('tickets').' where ticket_confirmed=0 and ticket_from_uid ='.$uid;
		$ret = $xoopsDB->query($sql);
		list($count) = $xoopsDB->fetchRow($ret);
		return $count;
	}}

	if (!function_exists('NumWaitingPayment')){
	function NumWaitingPayment($uid){
		global $xoopsDB;
		$sql = 'select count(*) as rec_count from '.$xoopsDB->prefix('tickets').' where ticket_confirmed=1 and ticket_payment_made=0 and ticket_from_uid ='.$uid;
		$ret = $xoopsDB->query($sql);
		list($count) = $xoopsDB->fetchRow($ret);
		return $count;
	}}

	if (!function_exists('NumCompleted')){
	function NumCompleted($uid){
		global $xoopsDB;
		$sql = 'select count(*) as rec_count from '.$xoopsDB->prefix('tickets').' where ticket_confirmed=1 and ticket_payment_made>0 and ticket_from_uid ='.$uid;
		$ret = $xoopsDB->query($sql);
		list($count) = $xoopsDB->fetchRow($ret);
		return $count;
	}}

global $xoopsUser;
if (isset($xoopsUser)&&!empty($xoopsUser)){
$modversion['sub'][1]['name'] = 'To Confirm ('.NumToConfirm($xoopsUser->uid()).')';
$modversion['sub'][1]['url'] = "?op=confirmmonies&numperpage=20";
$modversion['sub'][2]['name'] = 'Waiting ('.NumWaitingPayment($xoopsUser->uid()).')';
$modversion['sub'][2]['url'] = "?op=waiting&numperpage=20";
$modversion['sub'][3]['name'] = 'Paid ('.NumCompleted($xoopsUser->uid()).')';
$modversion['sub'][3]['url'] = "?op=paid&numperpage=20";
}

// Templates
$modversion['templates'][1]['file'] = 'makepayment_invoice.html';
$modversion['templates'][1]['description'] = 'Invoice Display';
$modversion['templates'][2]['file'] = 'makepayment_disclaimer.html';
$modversion['templates'][2]['description'] = 'General Disclaimer for making a payment';
$modversion['templates'][3]['file'] = 'makepayment_default.html';
$modversion['templates'][3]['description'] = 'Default Payment Page';
$modversion['templates'][4]['file'] = 'makepayment_payment.html';
$modversion['templates'][4]['description'] = 'Payment Made or Making Template';
$modversion['templates'][5]['file'] = 'makepayment_makepayment.html';
$modversion['templates'][5]['description'] = 'Payment Screen for Card Details';
$modversion['templates'][6]['file'] = 'makepayment_soapme.html';
$modversion['templates'][6]['description'] = 'Soap Test Script';
$modversion['templates'][7]['file'] = 'makepayment_makepayment_echeck.html';
$modversion['templates'][7]['description'] = 'Payment Screen for eCheck';

// Menu
$modversion['hasMain'] = 1;

// $xoopsModuleConfig['MerchantID']
$modversion['config'][1]['name'] = 'MerchantID';
$modversion['config'][1]['title'] = 'PAY_MERCHANTID';
$modversion['config'][1]['description'] = 'PAY_MERCHANTID_DESC';
$modversion['config'][1]['formtype'] = 'textbox';
$modversion['config'][1]['valuetype'] = 'text';
$modversion['config'][1]['default'] = '000000000-00000-0000-00000-0000000000000';

// $xoopsModuleConfig['ReturnPass']
$modversion['config'][2]['name'] = 'ReturnPass';
$modversion['config'][2]['title'] = 'PAY_RETURNPASS';
$modversion['config'][2]['description'] = 'PAY_RETURNPASS_DESC';
$modversion['config'][2]['formtype'] = 'textbox';
$modversion['config'][2]['valuetype'] = 'text';
$modversion['config'][2]['default'] = 'Return';

// $xoopsModuleConfig['CancelPass']
$modversion['config'][3]['name'] = 'CancelPass';
$modversion['config'][3]['title'] = 'PAY_CANCELPASS';
$modversion['config'][3]['description'] = 'PAY_CANCELPASS_DESC';
$modversion['config'][3]['formtype'] = 'textbox';
$modversion['config'][3]['valuetype'] = 'text';
$modversion['config'][3]['default'] = 'Cancel';

// $xoopsModuleConfig['AllowsCC']
$modversion['config'][4]['name'] = 'AllowsCC';
$modversion['config'][4]['title'] = 'PAY_ALLOWCC';
$modversion['config'][4]['description'] = 'PAY_ALLOWCC_DESC';
$modversion['config'][4]['formtype'] = 'yesno';
$modversion['config'][4]['valuetype'] = 'int';
$modversion['config'][4]['default'] = '1';

// $xoopsModuleConfig['AllowsECH']
$modversion['config'][5]['name'] = 'AllowsECH';
$modversion['config'][5]['title'] = 'PAY_ALLOWECH';
$modversion['config'][5]['description'] = 'PAY_ALLOWECH_DESC';
$modversion['config'][5]['formtype'] = 'yesno';
$modversion['config'][5]['valuetype'] = 'int';
$modversion['config'][5]['default'] = '1';

// $xoopsModuleConfig['EmailPayee']
$modversion['config'][6]['name'] = 'EmailPayee';
$modversion['config'][6]['title'] = 'PAY_ALLOWEMAIL';
$modversion['config'][6]['description'] = 'PAY_ALLOWEMAIL_DESC';
$modversion['config'][6]['formtype'] = 'yesno';
$modversion['config'][6]['valuetype'] = 'int';
$modversion['config'][6]['default'] = '1';

// $xoopsModuleConfig['AllowVisa']
$modversion['config'][7]['name'] = 'AllowVisa';
$modversion['config'][7]['title'] = 'PAY_ALLOWVISA';
$modversion['config'][7]['description'] = 'PAY_ALLOWVISA_DESC';
$modversion['config'][7]['formtype'] = 'yesno';
$modversion['config'][7]['valuetype'] = 'int';
$modversion['config'][7]['default'] = '1';

// $xoopsModuleConfig['AllowMastercard']
$modversion['config'][8]['name'] = 'AllowMastercard';
$modversion['config'][8]['title'] = 'PAY_ALLOWMC';
$modversion['config'][8]['description'] = 'PAY_ALLOWMC_DESC';
$modversion['config'][8]['formtype'] = 'yesno';
$modversion['config'][8]['valuetype'] = 'int';
$modversion['config'][8]['default'] = '1';

// $xoopsModuleConfig['AllowAmex']
$modversion['config'][9]['name'] = 'AllowAmex';
$modversion['config'][9]['title'] = 'PAY_ALLOWAMEX';
$modversion['config'][9]['description'] = 'PAY_ALLOWAMEX_DESC';
$modversion['config'][9]['formtype'] = 'yesno';
$modversion['config'][9]['valuetype'] = 'int';
$modversion['config'][9]['default'] = '0';

// $xoopsModuleConfig['AllowDiscover']
$modversion['config'][10]['name'] = 'AllowDiscover';
$modversion['config'][10]['title'] = 'PAY_ALLOWDISCOVER';
$modversion['config'][10]['description'] = 'PAY_ALLOWDISCOVER_DESC';
$modversion['config'][10]['formtype'] = 'yesno';
$modversion['config'][10]['valuetype'] = 'int';
$modversion['config'][10]['default'] = '0';

// $xoopsModuleConfig['AllowDiners']
$modversion['config'][11]['name'] = 'AllowDiners';
$modversion['config'][11]['title'] = 'PAY_ALLOWDINERS';
$modversion['config'][11]['description'] = 'PAY_ALLOWDINERS_DESC';
$modversion['config'][11]['formtype'] = 'yesno';
$modversion['config'][11]['valuetype'] = 'int';
$modversion['config'][11]['default'] = '0';

// $xoopsModuleConfig['AllowUnknown']
$modversion['config'][12]['name'] = 'AllowUnknown';
$modversion['config'][12]['title'] = 'PAY_ALLOWUNKNOWN';
$modversion['config'][12]['description'] = 'PAY_ALLOWUNKNOWN_DESC';
$modversion['config'][12]['formtype'] = 'yesno';
$modversion['config'][12]['valuetype'] = 'int';
$modversion['config'][12]['default'] = '0';

?>
