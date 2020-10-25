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

	// Admin menus
	define('MIC_MD_ADMINMENU1', 'Processed Refunds');
	define('MIC_MD_ADMINMENU2', 'Transactions');
	define('MIC_MD_ADMINMENU3', 'Periodic Payments');
	define('MIC_MD_ADMINMENU4', 'Preferences');
	
	//Xoop_version.php
	define('PAY_MERCHANTID','Merchant ID');
	define('PAY_MERCHANTID_DESC','Merchant ID from Intabill');
	define('PAY_RETURNPASS','Return Password');
	define('PAY_RETURNPASS_DESC','Password for POST on return');
	define('PAY_CANCELPASS','Cancel Password');
	define('PAY_CANCELPASS_DESC','Password for POST on cancel');
	define('PAY_ALLOWCC','Allow Credit Card');
	define('PAY_ALLOWCC_DESC','');		
	define('PAY_ALLOWECH','Allow eCheck Payment');
	define('PAY_ALLOWECH_DESC','');		
	define('PAY_ALLOWEMAIL','Send Payee Email');
	define('PAY_ALLOWEMAIL_DESC','');		
	define('PAY_ALLOWVISA','Allow Visa');
	define('PAY_ALLOWVISA_DESC','Allow for a Visa Card to be Processed');		
	define('PAY_ALLOWMC','Allow Mastercard');
	define('PAY_ALLOWMC_DESC','Allow for a Mastercard Card to be Processed');		
	define('PAY_ALLOWAMEX','Allow Amex');
	define('PAY_ALLOWAMEX_DESC','Allow for a Amex Card to be Processed');		
	define('PAY_ALLOWDISCOVER','Allow Discover');
	define('PAY_ALLOWDISCOVER_DESC','Allow for a Discover Card to be Processed');		
	define('PAY_ALLOWDINERS','Allow Diners International');
	define('PAY_ALLOWDINERS_DESC','Allow for a Diners International Card to be Processed');		
	define('PAY_ALLOWUNKNOWN','Allow Unknown Cards');
	define('PAY_ALLOWUNKNOWN_DESC','Allow for a Unknown cards to be Processed');		

?>