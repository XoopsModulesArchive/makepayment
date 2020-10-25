Module: Send Money

Merchant: Intabill

Version: 0.52

Author: Simon Roberts (simon@chronolabs.org.au)

Compatible: 2.0.14 – 2.0.16

--[ Notes ]--------------------------------------------------------------------

This is the first version released for makepayment for xoops for handling a payment via the credit cards available with Intabill, you will need to fill out the merchant application contract forms, scan them and email them back to me for me to set up a merchant account with intabill for you. My rates start at 3.5% upto around 10% for adult services like escorting.

We are always looking for people to become involved with developing paythem which you can also use with your own intabill merchant ID, this will ensure that the moneys are transferred directly to your account. You will need to add the following lines to your theme, for the warning dialogue box, to appear at the top of the page, this is for transactional information to be handled with your service.

This module utilizes a GPL Soap class library called nuSoap to do the echeck or credit card transactions; with an intabill merchant account you have your own test credit and echeck numbers. We will be writing the refund module for xoops soon, but for the moment this can be handled through the intabill terminal.

Good Luck!!

Simon (wishcraft)

Additional theme data:

------------------------------------------------------------------------------

<{if $warnings > 0}>
<table width="100%"  border="1" cellpadding="1" cellspacing="0" bordercolor="#000000">
<{foreach item=w_items from=$warningitems}>
  <tr bordercolor="#333333" bgcolor="#FFFF66">
    <td width="3%" nowrap="nowrap"><div align="center"><{$w_items.imgurl}></div></td>
    <td width="97%" nowrap="nowrap"><{$w_items.message}></td>
  </tr>
<{/foreach}>
</table>
<{/if}>

------------------------------------------------------------------------------