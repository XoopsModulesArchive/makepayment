<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<title>Demostration Payment Gateway</title>
</head>

<body>

<form action="/modules/makepayment/?op=checkin" method="post" name="payment" id="payment">
  <input type="submit" name="Submit" value="Submit Via Post">
  <input type="hidden" name="primarycurrency" value="AUD">
  <input type="hidden" name="payee_emailaddy" value="lsd25@hotmail.com">
  <input type="hidden" name="trigger" value="http://www.paythem.biz/trigger.php?transactionid=%transid%&trigger_pass=%trigger_pass%">
  <input type="hidden" name="return" value="http://www.paythem.biz/return.php?transactionid=%transid%&trigger_pass=%trigger_pass%">
  <input type="hidden" name="cancel" value="http://www.paythem.biz">
  <input type="hidden" name="itemname_1" value="Test Product 1">
  <input type="hidden" name="quanity_1" value="1">
  <input type="hidden" name="unitprice_1" value="19.95">
  <input type="hidden" name="currency_1" value="AUD">
  <input type="hidden" name="itemname_2" value="Test Product 2">
  <input type="hidden" name="quanity_2" value="3">
  <input type="hidden" name="unitprice_2" value="9.95">
  <input type="hidden" name="currency_2" value="AUD">
  <input type="hidden" name="itemname_3" value="Test Product 4">
  <input type="hidden" name="quanity_3" value="10">
  <input type="hidden" name="unitprice_3" value="1.95">
  <input type="hidden" name="currency_3" value="AUD">

</form>

</body>
</html>
