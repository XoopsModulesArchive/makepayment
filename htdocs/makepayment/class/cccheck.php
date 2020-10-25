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

function validateCC($ccnum)
{
    // Clean up input  
    $ccnum = ereg_replace('[-[:space:]]', '',$ccnum); 

    // What kind of card do we have
    $type = check_type($ccnum);

    // Does the number matchup ?
    $valid = check_number($ccnum);

    return [$type, $valid, $ccnum];
}

// Prefix and Length checks
function check_type( $cardnumber )
{
    $cardtype = 'Unknown';

    $len = mb_strlen($cardnumber);
    if ( 15 == $len && ('36' == mb_substr($cardnumber, 0, 1) || '38' == mb_substr($cardnumber, 0, 1)) ) {
        $cardtype = 'Diners';
    } elseif ( 15 == $len && '3' == mb_substr($cardnumber, 0, 1) ) {
        $cardtype = 'Amex';
    } elseif ( 16 == $len && ('6334' == mb_substr($cardnumber, 0, 4) || '6767' == mb_substr($cardnumber, 0, 4))) {
        $cardtype = 'Solo';
    } elseif ( 16 == $len && '6011' == mb_substr($cardnumber, 0, 4) ) {
        $cardtype = 'Discover';
    } elseif ( 16 == $len && '5' == mb_substr($cardnumber, 0, 1)  ) {
        $cardtype = 'MasterCard';
    } elseif ( (16 == $len || 13 == $len) && '4' == mb_substr($cardnumber, 0, 1) ) {
        $cardtype = 'Visa';
    }

    return ( $cardtype );
}

// MOD 10 checks 
function check_number( $cardnumber )
{
    $dig = toCharArray($cardnumber); 
    $numdig = count ($dig); 
    $j = 0; 
    for ($i = ($numdig - 2); $i >= 0; $i -= 2) {
        $dbl[$j] = $dig[$i] * 2; 
        $j++;
    }     
    $dblsz = count($dbl); 
    $validate = 0; 
    for ($i = 0; $i < $dblsz; $i++) {
        $add = toCharArray($dbl[$i]); 
        for ($j = 0; $j < count($add); $j++) {
            $validate += $add[$j];
        } 
        $add = '';
    } 
    for ($i = ($numdig - 1); $i >= 0; $i -= 2) {
        $validate += $dig[$i];
    } 
    if ('0' == mb_substr($validate, -1, 1)) {
        return 1;
    }

    return 0;
} 

// takes a string and returns an array of characters 

function toCharArray($input)
{
    $len = mb_strlen($input); 
    for ($j = 0; $j < $len; $j++) {
        $char[$j] = mb_substr($input, $j, 1);
    }
 
    return ($char);
} 

?> 

