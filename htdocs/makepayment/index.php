<?
require_once dirname(__DIR__, 2) . '/mainfile.php';
error_reporting(E_ALL);
require_once __DIR__ . '/include/uservalidation.php';
require_once __DIR__ . '/include/ticketvalidation.php';
require_once __DIR__ . '/class/cccheck.php';

global $xoopsTpl, $xoopsDB, $xoopsUser, $xoopsModuleConfig, $xoopsConfig;
require XOOPS_ROOT_PATH.'/header.php';

	if (isset($xoopsUser)&&!empty($xoopsUser)){
		$xoopsTpl->assign('xoops_user',1);
	} else {
		$xoopsTpl->assign('xoops_user',0);
	}

	if (isset($_GET)) {
		foreach ( $_GET as $k => $v ) {
			${$k} = $v;
		}
	}
	if (isset($_POST)) {
		foreach ( $_POST as $k => $v ) {
			${$k} = $v;
			$xoopsTpl->assign($k,$v);
		}
	} 				
	
	
	$ticket = $_SESSION['ticket'];
	$xoopsTpl->assign('ticket_crc',$ticket);
	$xoopsTpl->assign('MerchantID',$xoopsModuleConfig['MerchantID']);
	$xoopsTpl->assign('AllowMastercard',$xoopsModuleConfig['AllowMastercard']);
	$xoopsTpl->assign('AllowVisa',$xoopsModuleConfig['AllowVisa']);
	$xoopsTpl->assign('AllowDiners',$xoopsModuleConfig['AllowDiners']);
	$xoopsTpl->assign('AllowAmex',$xoopsModuleConfig['AllowAmex']);
	$xoopsTpl->assign('AllowDiscover',$xoopsModuleConfig['AllowDiscover']);
	
	switch($_GET['op'])
    {
			
        default :
			error_reporting(E_ALL);
			if (isset($_GET)) {
				foreach ( $_GET as $k => $v ) {
					${$k} = $v;
				}
			}
			
			if (strlen($FirstName)>0&&strlen($LastName)>0&&strlen($Address)>0&&strlen($Country)>0&&strlen($State)>0&&strlen($ZipCode)>0&&strlen($Phone)>0&&strlen($TransactionID)>0&&strlen($Product)>0&&strlen($seed)>0&&strlen($custom)>0)
			{
				$ticket_id = substr($custom,0,strpos($custom,'-'));
				$sessionid = substr($custom,strpos($custom,'-')+1,strlen($custom)-strpos($custom,'-'));
				//session_id($session_id);
				$ticket_crc = ticketvar($ticket_id,'ticket_crc');
				$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
seed, custom) values('$ticket_id', '$ticket_crd', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '$TransactionID', '$Product', '$SiteID', '$PolicyID', '$Amount', '$Currency', '$Status', 
'$seed', '$custom','$Message')";

				$ret=$xoopsDB->query($sql);
				if ($Status==1){
					$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = 1,ticket_payee_emailaddy ='$Email',InternStatus='Paid' where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
					$ret=$xoopsDB->query($sql);
					$ret_url=ticketvar($ticket_id,'ticket_return_url');
					
				} else {
					$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = -1,ticket_payee_emailaddy ='$Email',InternStatus='Declined' where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
					$ret=$xoopsDB->query($sql);
					$ret_url=ticketvar($ticket_id,'ticket_cancel_url');
				}
				$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
				$ret_url=str_replace('%trigger_pass%',md5($TransactionID.$custom.$xoopsModuleConfig['ReturnPass']),$ret_url);
				$ret_url=str_replace('%custom%',$custom,$ret_url);
				$ret_url=str_replace('%message%',$result['Message'],$ret_url);

				redirect_header($ret_url,0,'Returning you to merchant.');
				
			} else {
				$GLOBALS['xoopsOption']['template_main'] = 'makepayment_default.html';
			
				$xoopsTpl->assign("xoops_pagetitle",'Make a Payment via $xoopsConfig[sitename]');
				$xoopsTpl->assign("default_userid",$paythemid);
				$xoopsTpl->assign("default_description",$description);
				$xoopsTpl->assign("default_amount",$amount);
				if (isset($xoopsUser)&&!empty($xoopsUser)){
					$xoopsTpl->assign("default_email",$xoopsUser->getVar('email'));
				} else {
					redirect_header(XOOPS_URL.'/register.php',0,'You need to register with us to send money.');
				}
				break;
			}
		case 'soapme':
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_soapme.html';
			error_reporting(E_ALL);
			$ticket_id = validateticket($_SESSION['ticket'],$user_b);				
			if ($ticket_id <>0) {
				require_once __DIR__ . '/class/nusoap/nusoap.php';			
				$client = new soapclient('http://service.merchlogin.com/?wsdl', true,
						$proxyhost, $proxyport, $proxyusername, $proxypassword);
				
				$err = $client->getError();
				if ($err) {
					$xoopsTpl->assign("output1",'<h2>Constructor error</h2><pre>' . $err . '</pre>');
				}
				
				error_reporting(E_ALL);
				if ($func=='ProcessCreditCardV2')
				{
					if ($State = 'NA') {
						$rState = $OtherState;
					} else {
						$rState = $State;
					}
					
					if ($FirstName=='FIRST_NAME'&$LastName=='LAST_NAME'&&$Phone=='PHONE'&&$Address=='ADDRESS'&&
						$City=='Sydney'&&$rState=='NSW'&&$Country=='AU'&&$ZipCode=='2000'&&
						$Email=='EMAIL@EMAIL.COM'&&$CardNumber=='4234423442344234'&&$CC_Name=='NAME ON CARD'&&$ExpiryMonth=='01'&&
						$ExpiryYear=='2007'&&$CardCVV=='111'){
						
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Test Code Entered - RESPONSE Given.</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								
								$TransactionID = 'TESTT-ESTTEST-TESTTEST-TEST';
								
								$ret_url=ticketvar($ticket_id,'ticket_return_url');
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['ReturnPass'],$ret_url);
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['CancelPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								
								$_SESSION['ticket'] ='';
								
								$ret_urlb=ticketvar($ticket_id,'ticket_cancel_url');
								$ret_urlb=str_replace('%payment%',$xoopsModuleConfig['CancelPass'],$ret_urlb);
								$ret_urlb=str_replace('%transid%',$TransactionID,$ret_urlb);
								$ret_urlb=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_urlb);
								$ret_urlb=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_urlb);
								$ret_urlb=str_replace('%message%',$result['Message'],$ret_urlb);
								
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '0', 
'0','System Test','Test Code Entered -".date('Y-m-d h:i:s')."','Error','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = '';
								
								echo 'Return Path for Purchase Successful : <a href="'.$ret_url.'">'.basename($ret_url).'</a></br>';
								echo 'Return Path for Purchase Unsuccessful : <a href="'.$ret_urlb.'">'.basename($ret_urlb).'</a></br>';
								require XOOPS_ROOT_PATH.'/footer.php';
								exit;
						}
					


					list($type, $valid, $CardNumber) = validateCC($CardNumber);
					
					if (!$xoopsModuleConfig['Allow'.$type]){
						$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
						$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
						$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
						$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
						$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
						$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
						$xoopsTpl->assign("required",1);
						$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
						$xoopsTpl->assign("warnings",10);
						$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
						$warnings[1]['message']='<strong>'.$type.' credit card not allowed! Please fill in credit card displayed!</strong>';
						$xoopsTpl->assign("warningitems",$warnings);
						require XOOPS_ROOT_PATH.'/footer.php';
						$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
		rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '0', 
		'0','System Test','Test Code Entered -".date('Y-m-d h:i:s')."','CRC Mismatch','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
		$ret=$xoopsDB->query($sql);
					exit;					
					}
					
					if ($valid) {
						
						if (strlen($CardNumber)>0&&strlen($CC_Name)>0&&strlen($CardCVV)>0&&strlen($ExpiryMonth)>0&&
						    strlen($ExpiryYear)>0&&strlen($ZipCode)>0&&strlen($rState)>0&&strlen($Email)>0&&
							strlen($Phone)>0&&strlen($FirstName)>0&&strlen($LastName)>0&&strlen($turingKey)>0){
						
								
								$param = array('merchantId'=>$xoopsModuleConfig['MerchantID'],
												'IPAddress'=>$_SERVER['REMOTE_ADDR'],
												'submittedAmount'=>round(ticketvar($ticket_id,'ticket_totalvalue'),2),
												'merchantReference'=>ticketvar($ticket_id,'ticket_crc'),
												'clientFirstname'=>$FirstName,
												'clientSurname'=>$LastName,
												'clientPhone'=>$Phone,
												'clientStreet'=>$Address,
												'clientCity'=>$City,
												'clientState'=>$rState,
												'clientCountry'=>$Country,
												'clientPostcode'=>$ZipCode,
												'clientEmail'=>$Email,
												'product'=>$xoopsConfig['sitename']." Invoice Ticket ".$ticket_id,
												'cardNumber'=>$CardNumber,
												'cardName'=>$CC_Name,
												'expiryMonth'=>$ExpiryMonth,
												'expiryYear'=>$ExpiryYear,
												'cardCVV'=>$CardCVV,
												'currencyID'=>ticketvar($ticket_id,'ticket_currency'),
												'turingKey'=>$turingKey);
								
								$result = $client->call($func, $param, '', '', false, true);
								
						} else {
							$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
					
							$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
							$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
							$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
							$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
							$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
							$xoopsTpl->assign("required",1);
							$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
							$xoopsTpl->assign("warnings",10);
							$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
							$warnings[1]['message']='<strong>Missing Required Fields! Please fill in your details correctly to the forms requirement.</strong>';
							$xoopsTpl->assign("warningitems",$warnings);
							require XOOPS_ROOT_PATH.'/footer.php';

							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '0', 
'0','System Test','Test Code Entered -".date('Y-m-d h:i:s')."','Required Missing','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
														$ret=$xoopsDB->query($sql);

							exit;
				
					}										
				} else {
					$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
					$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
					$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
					$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
					$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
					$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
					$xoopsTpl->assign("required",1);
					$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
					$xoopsTpl->assign("warnings",10);
					$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
					$warnings[1]['message']='<strong>CRC Mismatch for a '.$type.' credit card! Please fill in credit card details correctly!</strong>';
					$xoopsTpl->assign("warningitems",$warnings);
					require XOOPS_ROOT_PATH.'/footer.php';
					$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
	rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '0', 
	'0','System Test','Test Code Entered -".date('Y-m-d h:i:s')."','CRC Mismatch','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
	$ret=$xoopsDB->query($sql);
				exit;
				}
					if ($client->fault) {
						$xoopsTpl->assign("output1",'<h2>Fault</h2><pre>'.print_r($result).'</pre>');
					} else {
						// Check for errors
						$err = $client->getError();
						if ($err) {
							// Display the error
							$xoopsTpl->assign("output1",'<h2>Error</h2><pre>' . $err . '</pre>');
						} else {
							$TransactionID=$result['TransactionID'];
							switch ($result['Status'])
							{
							case 0: //Declined
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus,CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Declined','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Payment was declined! Please contact your bank for further details.</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 2: // Error
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Error','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>An Error Occured With the Credit transaction please attempt to process again!</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 3://Pending
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Pending','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";

								$ret=$xoopsDB->query($sql);
								
								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = 2, ticket_closed = 1, ticket_confirmed=1, ticket_timestamp = CURRENT_TIMESTAMP() where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_return_url');
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['ReturnPass'],$ret_url);
								
//								$sql = 'update '.$xoopsDB->prefix('users').' set user_moneyin = user_moneyin + '.ticketvar($ticket_id,'ticket_totalvalue').' where uid = '.ticketvar($ticket_id,'ticket_to_uid');
//								$ret=$xoopsDB->query($sql);

								$xoopsMailer =& getMailer();
								$xoopsMailer->useMail();
								$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
								$xoopsMailer->setTemplate('paymentmade.tpl');
								$xoopsMailer->assign('TICKET_ID', $ticket_id);
								$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
								$xoopsMailer->assign('FIRSTNAME', $FirstName);
								$xoopsMailer->assign('LASTNAME', $LastName);
								$xoopsMailer->assign('ADDRESS', $Address);
								$xoopsMailer->assign('CITY', $City);
								$xoopsMailer->assign('COUNTRY', $Country);
								$xoopsMailer->assign('STATE', $State);
								$xoopsMailer->assign('POSTCODE', $ZipCode);
								$xoopsMailer->assign('PHONE', $Phone);
								$xoopsMailer->assign('EMAIL', $Email);
								$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
								$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
								$xoopsMailer->assign('CURRENCY', $param['currencyID']);
								$xoopsMailer->assign('STATUS', $result['Status']);
								$xoopsMailer->assign('STATE', $result['State']);
								$xoopsMailer->assign('MESSAGE', $result['Message']);
								$xoopsMailer->assign('TECHNICAL', $result['Technical']);
								$xoopsMailer->assign('INTSTATE', 'Pending');
								$xoopsMailer->assign('CCNAME', $CC_Name);
								$xoopsMailer->assign('CCTYPE', $type);
								$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
								$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
								$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
								$xoopsMailer->setToUsers(new XoopsUser(ticketvar($ticket_id,'ticket_to_uid')));
								$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
								$xoopsMailer->setFromName($xoopsConfig['sitename']);
								$xoopsMailer->setSubject("Merchant Payment Via ".$xoopsConfig['sitename']);
								$xoopsMailer->send(); 
								
								if ($xoopsModuleConfig['EmailPayee']==1){
									$xoopsMailer =& getMailer();
									$xoopsMailer->useMail();
									$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
									$xoopsMailer->setTemplate('paymentmade_customer.tpl');
									$xoopsMailer->assign('TICKET_ID', $ticket_id);
									$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
									$xoopsMailer->assign('FIRSTNAME', $FirstName);
									$xoopsMailer->assign('LASTNAME', $LastName);
									$xoopsMailer->assign('ADDRESS', $Address);
									$xoopsMailer->assign('CITY', $City);
									$xoopsMailer->assign('COUNTRY', $Country);
									$xoopsMailer->assign('STATE', $State);
									$xoopsMailer->assign('POSTCODE', $ZipCode);
									$xoopsMailer->assign('PHONE', $Phone);
									$xoopsMailer->assign('EMAIL', $Email);
									$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
									$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
									$xoopsMailer->assign('CURRENCY', $param['currencyID']);
									$xoopsMailer->assign('STATUS', $result['Status']);
									$xoopsMailer->assign('STATE', $result['State']);
									$xoopsMailer->assign('MESSAGE', $result['Message']);
									$xoopsMailer->assign('TECHNICAL', $result['Technical']);
									$xoopsMailer->assign('INTSTATE', 'Pending');
									$xoopsMailer->assign('CCNAME', $CC_Name);
									$xoopsMailer->assign('CCTYPE', $type);
									$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
									$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
									$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
									$xoopsMailer->setToEmails($Email);
									$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
									$xoopsMailer->setFromName($xoopsConfig['sitename']);
									$xoopsMailer->setSubject("Service/Product Payment Via ".$xoopsConfig['sitename']);
									$xoopsMailer->send(); 
								}
								
								$_SESSION['ticket'] ='';
								redirect_header($ret_url,0,'Payment is Pending - Returning you to merchant.');

								break;				
							case 4 ://Scrubbed
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Scrubbed','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Your payment was scrubbed, did you make a mistake with some details?</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 5: // Fraud
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Fraud')";
								$ret=$xoopsDB->query($sql);

								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = -1 where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_cancel_url');
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['CancelPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								
								$_SESSION['ticket'] ='';
								redirect_header($ret_url,0,'Payment was fraudulent - Returning you to merchant.');
								break;
							default: // Successful
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Successful','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";

								$ret=$xoopsDB->query($sql);

//								$sql = 'update '.$xoopsDB->prefix('users').' set user_moneyin = user_moneyin + '.ticketvar($ticket_id,'ticket_totalvalue').' where uid = '.ticketvar($ticket_id,'ticket_to_uid');
//								$ret=$xoopsDB->query($sql);

								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = 1 , ticked_closed = 1, ticket_confirmed=1, ticket_timestamp = CURRENT_TIMESTAMP() where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_return_url');
									
								$ret_url=str_replace('%transid%',$result['TransactionID'],$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['ReturnPass'],$ret_url);				
								$xoopsMailer =& getMailer();
								$xoopsMailer->useMail();
								$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
								$xoopsMailer->setTemplate('paymentmade.tpl');
								$xoopsMailer->assign('TICKET_ID', $ticket_id);
								$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
								$xoopsMailer->assign('FIRSTNAME', $FirstName);
								$xoopsMailer->assign('LASTNAME', $LastName);
								$xoopsMailer->assign('ADDRESS', $Address);
								$xoopsMailer->assign('CITY', $City);
								$xoopsMailer->assign('COUNTRY', $Country);
								$xoopsMailer->assign('STATE', $State);
								$xoopsMailer->assign('POSTCODE', $ZipCode);
								$xoopsMailer->assign('PHONE', $Phone);
								$xoopsMailer->assign('EMAIL', $Email);
								$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
								$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
								$xoopsMailer->assign('CURRENCY', $param['currencyID']);
								$xoopsMailer->assign('STATUS', $result['Status']);
								$xoopsMailer->assign('STATE', $result['State']);
								$xoopsMailer->assign('MESSAGE', $result['Message']);
								$xoopsMailer->assign('TECHNICAL', $result['Technical']);
								$xoopsMailer->assign('INTSTATE', 'Successful');
								$xoopsMailer->assign('CCNAME', $CC_Name);
								$xoopsMailer->assign('CCTYPE', $type);
								$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
								$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
								$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
								$xoopsMailer->setToUsers(new XoopsUser(ticketvar($ticket_id,'ticket_to_uid')));
								$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
								$xoopsMailer->setFromName($xoopsConfig['sitename']);
								$xoopsMailer->setSubject("Merchant Payment Via ".$xoopsConfig['sitename']);
								$xoopsMailer->send(); 
								
								if ($xoopsModuleConfig['EmailPayee']==1){
									$xoopsMailer =& getMailer();
									$xoopsMailer->useMail();
									$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
									$xoopsMailer->setTemplate('paymentmade_customer.tpl');
									$xoopsMailer->assign('TICKET_ID', $ticket_id);
									$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
									$xoopsMailer->assign('FIRSTNAME', $FirstName);
									$xoopsMailer->assign('LASTNAME', $LastName);
									$xoopsMailer->assign('ADDRESS', $Address);
									$xoopsMailer->assign('CITY', $City);
									$xoopsMailer->assign('COUNTRY', $Country);
									$xoopsMailer->assign('STATE', $State);
									$xoopsMailer->assign('POSTCODE', $ZipCode);
									$xoopsMailer->assign('PHONE', $Phone);
									$xoopsMailer->assign('EMAIL', $Email);
									$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
									$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
									$xoopsMailer->assign('CURRENCY', $param['currencyID']);
									$xoopsMailer->assign('STATUS', $result['Status']);
									$xoopsMailer->assign('STATE', $result['State']);
									$xoopsMailer->assign('MESSAGE', $result['Message']);
									$xoopsMailer->assign('TECHNICAL', $result['Technical']);
									$xoopsMailer->assign('INTSTATE', 'Successful');
									$xoopsMailer->assign('CCNAME', $CC_Name);
									$xoopsMailer->assign('CCTYPE', $type);
									$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
									$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
									$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
									$xoopsMailer->setToEmails($Email);
									$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
									$xoopsMailer->setFromName($xoopsConfig['sitename']);
									$xoopsMailer->setSubject("Service/Product Payment Via ".$xoopsConfig['sitename']);
									$xoopsMailer->send(); 
								}								
							$_SESSION['ticket'] ='';
							redirect_header($ret_url,0,'Payment was successful - Returning you to merchant.');
							}
							
						}
					}
				} elseif ($func=='ProcessCheckPayment')	{
					if ($State = 'NA') {
						$rState = $OtherState;
					} else {
						$rState = $State;
					}
					
					if ($FirstName=='FIRST_NAME'&$LastName=='LAST_NAME'&&$Phone=='PHONE'&&$Address=='ADDRESS'&&
						$City=='Sydney'&&$rState=='NSW'&&$Country=='AU'&&$ZipCode=='2000'&&
						$Email=='EMAIL@EMAIL.COM'&&$CardNumber=='4234423442344234'&&$CC_Name=='NAME ON CARD'&&$ExpiryMonth=='01'&&
						$ExpiryYear=='2007'&&$CardCVV=='111'){
						
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Test Code Entered - RESPONSE Given.</strong>';
								$xoopsTpl->assign("warningitems",$warnings);

								$ret_url=ticketvar($ticket_id,'ticket_cancel_url');
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['CancelPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								
								$_SESSION['ticket'] ='';
								$TransactionID = 'TESTT-ESTTEST-TESTTEST-TEST';
								$ret_urlb=ticketvar($ticket_id,'ticket_cancel_url');
								$ret_urlb=str_replace('%transid%',$TransactionID,$ret_urlb);
								$ret_urlb=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_urlb);
								$ret_urlb=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_urlb);
								$ret_urlb=str_replace('%message%',$result['Message'],$ret_urlb);
								
							$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '0', 
'0','System Test','Test Code Entered -".date('Y-m-d h:i:s')."','Error','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type',now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = '';
								
								echo 'Return Path for Purchase Successful : <a href="'.$ret_url.'">'.basename($ret_url).'</a></br>';
								echo 'Return Path for Purchase Unsuccessful : <a href="'.$ret_urlb.'">'.basename($ret_urlb).'</a></br>';
								require XOOPS_ROOT_PATH.'/footer.php';
								exit;
						}
					
					$param = array('merchantId'=>$xoopsModuleConfig['MerchantID'],
									'IPAddress'=>$_SERVER['REMOTE_ADDR'],
									'submittedAmount'=>round(ticketvar($ticket_id,'ticket_totalvalue'),2),
									'Reference'=>ticketvar($ticket_id,'ticket_crc'),
									'Firstname'=>$FirstName,
									'Lastname'=>$LastName,
									'Phone'=>$Phone,
									'Address'=>$Address,
									'City'=>$City,
									'State'=>$rState,
									'Country'=>$Country,
									'ZipCode'=>$ZipCode,
									'Email'=>$Email,
									'product'=>$xoopsConfig['sitename']." Invoice Ticket ".$ticket_id,
									'RoutingNumber'=>$RoutingNumber,
									'AccountNumber'=>$AccountNumber,
									'SSN'=>$SSN,
									'turingKey'=>$turingKey);
									
					$result = $client->call($func, $param, '', '', false, true);
					
					/*$xoopsTpl->assign("output1",'<p>'.print_r($param).'</p>');
					$xoopsTpl->assign("output2",'<p>'.print_r($result).'</p>');
					require dirname(__DIR__, 2) . '/footer.php';
					exit;
					*/
					// Check for a fault
					if ($client->fault) {
						$xoopsTpl->assign("output1",'<h2>Fault</h2><pre>'.print_r($result).'</pre>');
					} else {
						// Check for errors
						$err = $client->getError();
						if ($err) {
							// Display the error
							$xoopsTpl->assign("output1",'<h2>Error</h2><pre>' . $err . '</pre>');
						} else {
							$TransactionID=$result['TransactionID'];
							switch ($result['Status'])
							{
							case 0: //Declined
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Declined','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment_echeck.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Payment was declined! Please contact your bank for further details.</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 2: // Error
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Error','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment_echeck.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>An Error Occured With the Credit transaction please attempt to process again!</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 3://Pending
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Pending','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";

								$ret=$xoopsDB->query($sql);
								
								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = 2, ticket_closed = 1, ticket_confirmed=1, ticket_timestamp = CURRENT_TIMESTAMP() where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_return_url');
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['ReturnPass'],$ret_url);
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
				
//								$sql = 'update '.$xoopsDB->prefix('users').' set user_moneyin = user_moneyin + '.ticketvar($ticket_id,'ticket_totalvalue').' where uid = '.ticketvar($ticket_id,'ticket_to_uid');
//								$ret=$xoopsDB->query($sql);


								$xoopsMailer =& getMailer();
								$xoopsMailer->useMail();
								$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
								$xoopsMailer->setTemplate('echeck_paymentmade.tpl');
								$xoopsMailer->assign('TICKET_ID', $ticket_id);
								$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
								$xoopsMailer->assign('FIRSTNAME', $FirstName);
								$xoopsMailer->assign('LASTNAME', $LastName);
								$xoopsMailer->assign('ADDRESS', $Address);
								$xoopsMailer->assign('CITY', $City);
								$xoopsMailer->assign('COUNTRY', $Country);
								$xoopsMailer->assign('STATE', $State);
								$xoopsMailer->assign('POSTCODE', $ZipCode);
								$xoopsMailer->assign('PHONE', $Phone);
								$xoopsMailer->assign('EMAIL', $Email);
								$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
								$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
								$xoopsMailer->assign('CURRENCY', $param['currencyID']);
								$xoopsMailer->assign('STATUS', $result['Status']);
								$xoopsMailer->assign('STATE', $result['State']);
								$xoopsMailer->assign('MESSAGE', $result['Message']);
								$xoopsMailer->assign('TECHNICAL', $result['Technical']);
								$xoopsMailer->assign('INTSTATE', 'Pending');
								$xoopsMailer->assign('ROUTINGNUMBER', $RoutingNumber);
								$xoopsMailer->assign('ACCOUNTNUMBER', $AccountNumber);
								$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
								$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
								$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
								$xoopsMailer->setToUsers(new XoopsUser(ticketvar($ticket_id,'ticket_to_uid')));
								$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
								$xoopsMailer->setFromName($xoopsConfig['sitename']);
								$xoopsMailer->setSubject("Merchant Payment Via ".$xoopsConfig['sitename']);
								$xoopsMailer->send(); 
								if ($xoopsModuleConfig['EmailPayee']==1){
									$xoopsMailer =& getMailer();
									$xoopsMailer->useMail();
									$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
									$xoopsMailer->setTemplate('echeck_paymentmade_customer.tpl');
									$xoopsMailer->assign('TICKET_ID', $ticket_id);
									$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
									$xoopsMailer->assign('FIRSTNAME', $FirstName);
									$xoopsMailer->assign('LASTNAME', $LastName);
									$xoopsMailer->assign('ADDRESS', $Address);
									$xoopsMailer->assign('CITY', $City);
									$xoopsMailer->assign('COUNTRY', $Country);
									$xoopsMailer->assign('STATE', $State);
									$xoopsMailer->assign('POSTCODE', $ZipCode);
									$xoopsMailer->assign('PHONE', $Phone);
									$xoopsMailer->assign('EMAIL', $Email);
									$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
									$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
									$xoopsMailer->assign('CURRENCY', $param['currencyID']);
									$xoopsMailer->assign('STATUS', $result['Status']);
									$xoopsMailer->assign('STATE', $result['State']);
									$xoopsMailer->assign('MESSAGE', $result['Message']);
									$xoopsMailer->assign('TECHNICAL', $result['Technical']);
									$xoopsMailer->assign('INTSTATE', 'Pending');
									$xoopsMailer->assign('ROUTINGNUMBER', $RoutingNumber);
									$xoopsMailer->assign('ACCOUNTNUMBER', $AccountNumber);
									$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
									$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
									$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
									$xoopsMailer->setToEmails($Email);
									$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
									$xoopsMailer->setFromName($xoopsConfig['sitename']);
									$xoopsMailer->setSubject("Service/Product Payment Via ".$xoopsConfig['sitename']);
									$xoopsMailer->send(); 
								}
								
								$_SESSION['ticket'] ='';
								redirect_header($ret_url,0,'Payment is Pending - Returning you to merchant.');

								break;				
							case 4 ://Scrubbed
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Scrubbed','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";
								$ret=$xoopsDB->query($sql);

								$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment_echeck.html';
					
								$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
								$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
								$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
								$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
								$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
								$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
								$xoopsTpl->assign("warnings",10);
								$warnings[1]['imgurl']='<strong><img src="images/red-cross.gif"></strong>';
								$warnings[1]['message']='<strong>Your payment was scrubbed, did you make a mistake with some details?</strong>';
								$xoopsTpl->assign("warningitems",$warnings);
								break;
							case 5: // Fraud
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Fraud','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";
								$ret=$xoopsDB->query($sql);

								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = -1 where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_cancel_url');
								$ret_url=str_replace('%transid%',$TransactionID,$ret_url);
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['CancelPass'],$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['CancelPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
								
								$_SESSION['ticket'] ='';
								redirect_header($ret_url,0,'Payment was fraudulent - Returning you to merchant.');
								break;
							default: // Successful
								$sql = "insert into ".$xoopsDB->prefix('ticket_transactions'). " (ticket_id, ticket_crc, FirstName, LastName, Address, City, Country, State, ZipCode, Phone, Email, TransactionID, Product, SiteID, PolicyID, Amount, Currency, `Status`, 
rState,Message,Technical,InternalStatus, CC_CCV,CC_MD5_NUM,CC_ExpiryMonth,CC_ExpiryYear,CC_Name,CC_Type,ECHECK_SSN,ECHECK_ROUTING,ECHECK_ACCOUNT_MD5,DateCreated) values('$ticket_id', '$ticket_crc', '$FirstName', '$LastName', '$Address', '$City', '$Country', '$State', '$ZipCode', '$Phone', '$Email', '".$result['TransactionID']."', '".$param['product']."', '".$param['merchantId']."', '0', '".$param['submittedAmount']."', '".$param['currencyID']."', '".$result['Status']."', 
'".$result['State']."','".$result['Message']."','".$result['Technical']."','Successful','$CardCVV',md5('$CardNumber'),'$ExpiryMonth','$ExpiryYear','$CC_Name','$type','$SSN','$RoutingNumber',md5('$AccountNumber'),now())";

								$ret=$xoopsDB->query($sql);

	//							$sql = 'update '.$xoopsDB->prefix('users').' set user_moneyin = user_moneyin + '.ticketvar($ticket_id,'ticket_totalvalue').' where uid = '.ticketvar($ticket_id,'ticket_to_uid');
	//							$ret=$xoopsDB->query($sql);

								$sql= 'update '.$xoopsDB->prefix('tickets')." set ticket_payment_made = 1 , ticked_closed = 1, ticket_confirmed=1, ticket_timestamp = CURRENT_TIMESTAMP() where ticket_id = $ticket_id and ticket_crc = '$ticket_crc'";
								$ret=$xoopsDB->query($sql);
								$ret_url=ticketvar($ticket_id,'ticket_return_url');
									
								$ret_url=str_replace('%transid%',$result['TransactionID'],$ret_url);
								$ret_url=str_replace('%payment%',$xoopsModuleConfig['ReturnPass'],$ret_url);
								$ret_url=str_replace('%trigger_pass%',md5($TransactionID.ticketvar($ticket_id,'ticket_intabill_custom').$xoopsModuleConfig['ReturnPass']),$ret_url);
								$ret_url=str_replace('%custom%',ticketvar($ticket_id,'ticket_intabill_custom'),$ret_url);
								$ret_url=str_replace('%message%',$result['Message'],$ret_url);
			
								$xoopsMailer =& getMailer();
								$xoopsMailer->useMail();
								$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
								$xoopsMailer->setTemplate('echeck_paymentmade.tpl');
								$xoopsMailer->assign('TICKET_ID', $ticket_id);
								$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
								$xoopsMailer->assign('FIRSTNAME', $FirstName);
								$xoopsMailer->assign('LASTNAME', $LastName);
								$xoopsMailer->assign('ADDRESS', $Address);
								$xoopsMailer->assign('CITY', $City);
								$xoopsMailer->assign('COUNTRY', $Country);
								$xoopsMailer->assign('STATE', $State);
								$xoopsMailer->assign('POSTCODE', $ZipCode);
								$xoopsMailer->assign('PHONE', $Phone);
								$xoopsMailer->assign('EMAIL', $Email);
								$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
								$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
								$xoopsMailer->assign('CURRENCY', $param['currencyID']);
								$xoopsMailer->assign('STATUS', $result['Status']);
								$xoopsMailer->assign('STATE', $result['State']);
								$xoopsMailer->assign('MESSAGE', $result['Message']);
								$xoopsMailer->assign('TECHNICAL', $result['Technical']);
								$xoopsMailer->assign('INTSTATE', 'Successful');
								$xoopsMailer->assign('ROUTINGNUMBER', $RoutingNumber);
								$xoopsMailer->assign('ACCOUNTNUMBER', $AccountNumber);
								$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
								$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
								$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
								$xoopsMailer->setToUsers(new XoopsUser(ticketvar($ticket_id,'ticket_to_uid')));
								$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
								$xoopsMailer->setFromName($xoopsConfig['sitename']);
								$xoopsMailer->setSubject("Merchant Payment Via ".$xoopsConfig['sitename']);
								$xoopsMailer->send(); 
								
								if ($xoopsModuleConfig['EmailPayee']==1){
									$xoopsMailer =& getMailer();
									$xoopsMailer->useMail();
									$xoopsMailer->setTemplateDir(XOOPS_ROOT_PATH.'/modules/makepayment/language/english/mail/');
									$xoopsMailer->setTemplate('echeck_paymentmade_customer.tpl');
									$xoopsMailer->assign('TICKET_ID', $ticket_id);
									$xoopsMailer->assign('TICKET_CRC', $ticket_crc);
									$xoopsMailer->assign('FIRSTNAME', $FirstName);
									$xoopsMailer->assign('LASTNAME', $LastName);
									$xoopsMailer->assign('ADDRESS', $Address);
									$xoopsMailer->assign('CITY', $City);
									$xoopsMailer->assign('COUNTRY', $Country);
									$xoopsMailer->assign('STATE', $State);
									$xoopsMailer->assign('POSTCODE', $ZipCode);
									$xoopsMailer->assign('PHONE', $Phone);
									$xoopsMailer->assign('EMAIL', $Email);
									$xoopsMailer->assign('TRANSACTIONID', $result['TransactionID']);
									$xoopsMailer->assign('AMOUNT', $param['submittedAmount']);
									$xoopsMailer->assign('CURRENCY', $param['currencyID']);
									$xoopsMailer->assign('STATUS', $result['Status']);
									$xoopsMailer->assign('STATE', $result['State']);
									$xoopsMailer->assign('MESSAGE', $result['Message']);
									$xoopsMailer->assign('TECHNICAL', $result['Technical']);
									$xoopsMailer->assign('INTSTATE', 'Successful');
									$xoopsMailer->assign('ROUTINGNUMBER', $RoutingNumber);
									$xoopsMailer->assign('ACCOUNTNUMBER', $AccountNumber);
									$xoopsMailer->assign('SITENAME', $xoopsConfig['sitename']);
									$xoopsMailer->assign('ADMINMAIL', $xoopsConfig['adminmail']);
									$xoopsMailer->assign('SITEURL', XOOPS_URL."/");
									$xoopsMailer->setToEmails($Email);
									$xoopsMailer->setFromEmail($xoopsConfig['adminmail']);
									$xoopsMailer->setFromName($xoopsConfig['sitename']);
									$xoopsMailer->setSubject("Service/Product Payment Via ".$xoopsConfig['sitename']);
									$xoopsMailer->send(); 
								}
								
								$_SESSION['ticket'] ='';
								redirect_header($ret_url,0,'Payment was successful - Returning you to merchant.');
							}
							
						}
					}

				} else {
					$xoopsTpl->assign("output1",'no ticket id');
				}
			}
			break;			
		case 'makepayment':
			error_reporting(E_ALL);
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment.html';

			//print_r($user);
			$ticket_id = validateticket($_SESSION['ticket'],$user_b);		
			if ($ticket_id <>0){
				$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
				$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
				$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
				$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
				$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
				$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
			}
			break;
			
		case 'makepayment_echeck':
			error_reporting(E_ALL);
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_makepayment_echeck.html';

			//print_r($user);
			$ticket_id = validateticket($_SESSION['ticket'],$user_b);		
			if ($ticket_id <>0){
				$xoopsTpl->assign("intabill_siteid",ticketvar($ticket_id,'ticket_intabill_siteid'));
				$xoopsTpl->assign("intabill_custom",ticketvar($ticket_id,'ticket_intabill_custom'));
				$xoopsTpl->assign("intabill_seed",ticketvar($ticket_id,'ticket_intabill_seed'));
				$xoopsTpl->assign("cost_currency",ticketvar($ticket_id,'ticket_currency'));
				$xoopsTpl->assign("cost",ticketvar($ticket_id,'ticket_totalvalue'));
				$xoopsTpl->assign("producttext",ticketvar($ticket_id,'ticket_producttext'));
			}
			break;

		case 'confirm':
			
			$sql = 'update '.$xoopsDB->prefix('tickets')." set ticket_confirmed=1 where ticket_crc = '$ticket'";
			$ret = $xoopsDB->query($sql);
			redirect_header('?op=makepayment',0,'Redirecting you to the payment screen.');			
			break;
			
		case 'cancel_back2merchant':
			
			$sql = 'update '.$xoopsDB->prefix('tickets')." set ticket_confirmed=-1 where ticket_crc = '$ticket'";
			$ret = $xoopsDB->query($sql);
			
			redirect_header(ticketvar($ticket,'ticket_cancel_url','CRC'),0,'Redirecting you to merchants cancellation page.');			
			break;

		case 'cancel':
			
			$sql = 'update '.$xoopsDB->prefix('tickets')." set ticket_confirmed=-1 where ticket_crc = '$ticket'";
			$ret = $xoopsDB->query($sql);
			redirect_header('?',0,'Redirecting you to your payment portfolio.');			
			break;
			
		case 'confirmmonies':
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_payment.html';

			$xoopsTpl->assign("reporton",0);
			if ($start=='') { $start=0; }			
			if ($numperpage==''||$numperpage==0) {$numperpage=20;}
			$sql = 'select count(*) from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=0 and ticket_confirmed=0';
			$items=array();
			$ret=$xoopsDB->query($sql);
			list($count)=$xoopsDB->fetchRow($ret);
			$sql = 'select ticket_crc, ticket_to_uid, ticket_created, ticket_totalvalue, ticket_currency from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=0 and ticket_confirmed=0 limit '.$start.','.$numperpage;
			$ret=$xoopsDB->query($sql);
			while (list($ticket_crc, $ticket_to_uid, $ticket_created, $ticket_totalvalue, $ticket_currency)=$xoopsDB->fetchRow($ret)){
				$u++;
				$items[$u]['crc']=$ticket_crc;
				$items[$u]['uid']=$ticket_to_uid;
				$items[$u]['created']=$ticket_created;
				$items[$u]['ttl_value']=sprintf("%01.2f",$ticket_totalvalue).' '.$ticket_currency;
				$items[$u]['uname']=$xoopsUser->getUnameFromId($ticket_to_uid);
				$xoopsTpl->assign("reporton",1);
			}
			$xoopsTpl->assign("invoice_array",$items);
			
			for ($i=0;$i<$count;$i=$i+$numperpage){
				$page=$page+1;
				$pagenav .="<a href='".XOOPS_URL."/modules/makepayment/?op=waiting&start=$i&numperpage=$numperpage'>$page</a>&nbsp;";
			}
			$xoopsTpl->assign("page_nav",$pagenav);
			
			break;

		case 'waiting':
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_payment.html';

			$xoopsTpl->assign("reporton",0);
			if ($start=='') { $start=0; }			
			if ($numperpage==''||$numperpage==0) {$numperpage=20;}
			$sql = 'select count(*) from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=0 and ticket_confirmed=1';
			$items=array();
			$ret=$xoopsDB->query($sql);
			list($count)=$xoopsDB->fetchRow($ret);
			$sql = 'select ticket_crc, ticket_to_uid, ticket_created, ticket_totalvalue, ticket_currency from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=0 and ticket_confirmed=1 limit '.$start.','.$numperpage;
			$ret=$xoopsDB->query($sql);
			while (list($ticket_crc, $ticket_to_uid, $ticket_created, $ticket_totalvalue, $ticket_currency)=$xoopsDB->fetchRow($ret)){
				$u++;
				$items[$u]['crc']=$ticket_crc;
				$items[$u]['uid']=$ticket_to_uid;
				$items[$u]['created']=$ticket_created;
				$items[$u]['ttl_value']=sprintf("%01.2f",$ticket_totalvalue).' '.$ticket_currency;
				$items[$u]['uname']=$xoopsUser->getUnameFromId($ticket_to_uid);
				$xoopsTpl->assign("reporton",1);
			}
			$xoopsTpl->assign("invoice_array",$items);
			
			for ($i=0;$i<$count;$i=$i+$numperpage){
				$page=$page+1;
				$pagenav .="<a href='".XOOPS_URL."/modules/makepayment/?op=waiting&start=$i&numperpage=$numperpage'>$page</a>&nbsp;";
			}
			$xoopsTpl->assign("page_nav",$pagenav);
			
			break;
		case 'paid':
			$GLOBALS['xoopsOption']['template_main'] = 'makepayment_payment.html';

			if (isset($_GET)) {
				foreach ( $_GET as $k => $v ) {
					${$k} = $v;
				}
			}
			if (isset($_POST)) {
				foreach ( $_POST as $k => $v ) {
					${$k} = $v;
				}
			} 		
			$xoopsTpl->assign("reporton",0);		
			if ($start=='') { $start=0; }			
			if ($numperpage==''||$numperpage==0) {$numperpage=20;}
			$sql = 'select count(*) from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=1 and ticket_confirmed=1';
			$items=array();
			$ret=$xoopsDB->query($sql);
			list($count)=$xoopsDB->fetchRow($ret);
			$sql = 'select ticket_crc, ticket_to_uid, ticket_created, ticket_totalvalue, ticket_currency from '.$xoopsDB->prefix('tickets').' where ticket_from_uid = '.$xoopsUser->uid().' and ticket_payment_made=1 and ticket_confirmed=1 limit '.$start.','.$numperpage;
			$ret=$xoopsDB->query($sql);
			while (list($ticket_crc, $ticket_to_uid, $ticket_created, $ticket_totalvalue, $ticket_currency)=$xoopsDB->fetchRow($ret)){
				$u++;
				$items[$u]['crc']=$ticket_crc;
				$items[$u]['uid']=$ticket_to_uid;
				$items[$u]['created']=$ticket_created;
				$items[$u]['ttl_value']=sprintf("%01.2f",$ticket_totalvalue).' '.$ticket_currency;
				$items[$u]['uname']=$xoopsUser->getUnameFromId($ticket_to_uid);
				$xoopsTpl->assign("reporton",1);
			}
			$xoopsTpl->assign("invoice_array",$items);
			
			for ($i=0;$i<$count;$i=$i+$numperpage){
				$page=$page+1;
				$pagenav .="<a href='".XOOPS_URL."/modules/makepayment/?op=waiting&start=$i&numperpage=$numperpage'>$page</a>&nbsp;";
			}
			$xoopsTpl->assign("page_nav",$pagenav);
			
			break;
		
			break;
		case 'raisepayment':
			if (isset($_POST)) {
				foreach ( $_POST as $k => $v ) {
					${$k} = $v;
				}
			} else {
				if (isset($_GET)) {
					foreach ( $_GET as $k => $v ) {
						${$k} = $v;
					}
				}
			}

			$user = validateuser($paythemid);
			$user_b = validateuser($payfromperson);
			
			if ($user['uid']==$xoopsUser->uid()){
				redirect_header("?paythemid=$paythemid&amount=$amount&description=$description&currency=$currency",4,'You cannot make a payment to yourself');
				exit;
			}
			if ($user['uid']==0){
				redirect_header("?paythemid=$paythemid&amount=$amount&description=$description&currency=$currency",4,'That user does not exist on the system');
				exit;
			}
			if (!is_numeric($amount)){
				redirect_header('?paythemid=$paythemid&amount=$amount&description=$description&currency=$currency',4,'The amount you have entered is not numeric');
				exit;
			}
			if ($user['uid']<>0){
				$sql = "insert into ".$xoopsDB->prefix('tickets')." (ticket_to_uid, ticket_from_uid, ticket_ip, ticket_hostname, ticket_currency, ticket_payee_emailaddy, ticket_trigger_url, ticket_return_url, ticket_cancel_url, ticket_totalvalue, ticket_created) values ('".$user['uid']."','".$xoopsUser->uid()."','".$HTTP_SERVER_VARS['REMOTE_ADDR']."','".gethostbyaddr($HTTP_SERVER_VARS['REMOTE_ADDR'])."','$currency','$payfromperson','','','','$amount',concat(CURRENT_DATE,' ',CURRENT_TIME))";
				$ret=$xoopsDB->query($sql);
				$ticket_id = $xoopsDB->getInsertId();
				$ticket_crc = generate_ticketcrc($ticketid);
				$sql = "update ".$xoopsDB->prefix('tickets')." set ticket_crc = '$ticket_crc' where ticket_id = $ticket_id";
				$ret=$xoopsDB->query($sql);
				$ticket= array("name" =>$description, 'quanity'=>1,'unitprice'=>$amount,'currency'=>$currency);
				$sql = "insert into ".$xoopsDB->prefix('tickets_items')." (ticket_id, item_name, item_quanity, item_unitprice, item_currency, item_cateloguenum) values ('$ticket_id','".$ticket['name']."','".$ticket['quanity']."','".$ticket['unitprice']."','".$ticket['currency']."','')";
				$ret=$xoopsDB->query($sql);					
				$_SESSION['ticket'] = $ticket_crc;

			}			
			if ($ticket_id>0){
	     		redirect_header('?op=invoice&uid='.$user['uname'].'',0,'Redirecting you to invoice confirmation reciept');
			} else {
				redirect_header('?op=error',0,'An Error Has Occured Raising the Purchase ticket');
			}
			break;				
		case 'checkin' :
			global $xoopsDB;
			$uid = strlen($_GET['uid'])>0 ? $_GET['uid'] : 1;
			$user = validateuser($uid);
			if ($user['uid']<>0){
				$xoopsTpl->assign("payment_to",$user['name']);

				if (isset($_POST)) {
					foreach ( $_POST as $k => $v ) {
						${$k} = $v;
					}
				} else {
					if (isset($_GET)) {
						foreach ( $_GET as $k => $v ) {
							${$k} = $v;
						}
					} else {
						redirect_header('?op=error',0,'An Error Has Occured Raising the Purchase ticket');
						exit;
					}
				}
		
				$user_b = validateuser($payee_emailaddy);
		/*				
				$primarycurreny=$_GET['primarycurrency'];
				$payee_emailaddy=$_GET['payee_emailaddy'];
				$trigger_url=$_GET['trigger'];
				$return_url=$_GET.$xoopsModuleConfig['ReturnPass']];
				$cancel_url=$_GET.$xoopsModuleConfig['CancelPass']];
				
				$itemname=$_GET['itemname'];
				$quanity=$_GET['quanity'];
				$unitprice=$_GET['unitprice'];
				$currency=$_GET['currency'];
		*/		
				
				$sql = "insert into ".$xoopsDB->prefix('tickets')." (ticket_to_uid, ticket_from_uid, ticket_ip, ticket_hostname, ticket_currency, ticket_payee_emailaddy, ticket_trigger_url, ticket_return_url, ticket_cancel_url, ticket_created,ticket_intabill_siteid,ticket_intabill_custom,ticket_intabill_seed,ticket_sessionid) values ('".$user['uid']."','".$user_b['uid']."','".$HTTP_SERVER_VARS['REMOTE_ADDR']."','".gethostbyaddr($HTTP_SERVER_VARS['REMOTE_ADDR'])."','$primarycurrency','$payee_emailaddy','".addslashes($trigger)."','".addslashes($return)."','".addslashes($cancel)."',concat(CURRENT_DATE,' ',CURRENT_TIME), '$intabill_siteid','$intabill_custom','$intabill_seed','".session_id()."')";
				$ret=$xoopsDB->query($sql);
				$ticket_id = $xoopsDB->getInsertId();
				$ticket_crc = generate_ticketcrc($ticket_id);
				$_SESSION['ticket'] = $ticket_crc;
				if ($intabill_siteid==""){
					$intabill_siteid="19150f28-9215-102a-ba7a-00188b306089";
					$intabill_custom=$ticket_id.'-'.session_id();
					$intabill_seed=md5($ticket_id.$ticket_crc);
					$sql = "update ".$xoopsDB->prefix('tickets')." set ticket_crc = '$ticket_crc', ticket_intabill_siteid='$intabill_siteid', ticket_intabill_custom='$intabill_custom', ticket_intabill_seed='$intabill_seed' where ticket_id = $ticket_id";
				} else {
					$sql = "update ".$xoopsDB->prefix('tickets')." set ticket_crc = '$ticket_crc' where ticket_id = $ticket_id";
				}
				$ret=$xoopsDB->query($sql);
				$i=1;		
				while (isset(${'itemname_'.$i})&&isset(${'quanity_'.$i})&&isset(${'unitprice_'.$i})&&isset(${'currency_'.$i})){
					$ticket= array("name" =>${'itemname_'.$i}, 'quanity'=>${'quanity_'.$i},'unitprice'=>${'unitprice_'.$i},'currency'=>${'currency_'.$i});
					$sql = "insert into ".$xoopsDB->prefix('tickets_items')." (ticket_id, item_name, item_quanity, item_unitprice, item_currency, item_cateloguenum) values ('$ticket_id','".$ticket['name']."','".$ticket['quanity']."','".$ticket['unitprice']."','".$ticket['currency']."','')";
					$ret=$xoopsDB->query($sql);					
					$i++;
				}

				if ($i>2){
					$sql = "update ".$xoopsDB->prefix('tickets')." set ticket_producttext = '".($i-1)." Line items from 3rd Party Website' where ticket_id = $ticket_id";
				} else {
					$sql = "update ".$xoopsDB->prefix('tickets')." set ticket_producttext = '".$itemname_1."' where ticket_id = $ticket_id";				
				}
				$ret=$xoopsDB->query($sql);					

			}			
			if ($ticket_id>0){
	     		
					redirect_header('?op=invoice&uid='.$user['uname'].'',0,'Redirecting you to invoice confirmation reciept');
				
			} else {
				redirect_header('?op=error',0,'An Error Has Occured Raising the Purchase ticket');
			}
			break;
        case 'displayinvoice' :
			
            $GLOBALS['xoopsOption']['template_main'] = 'makepayment_invoice.html';
			$user_b = validateuser($xoopsUser->getUnameFromId($xoopsUser->uid()));
			//print_r($user);
			$ticket_id = validateticket($_SESSION['ticket'],$user_b);		
			if ($ticket_id <>0&&ticketvar($ticket_id,'ticket_from_uid')==$xoopsUser->uid()){
				$xoopsTpl->assign("payment_to",$xoopsUser->getUnameFromId(ticketvar($ticket_id,'ticket_to_uid'),1));
				$xoopsTpl->assign("xoops_pagetitle",'invoice number '.$ticket_id);
				if (ticketvar($ticket_id,'ticket_confirmed')>0){
					if (ticketvar($ticket_id,'ticket_payment_made')>0) {
						$xoopsTpl->assign("button_confirm",0); 
						$xoopsTpl->assign("button_cancel",0);
						$xoopsTpl->assign('cancel_url',XOOPS_URL.'/modules/makepayment/?op=cancel');
					} else {
						$xoopsTpl->assign("button_confirm",$xoopsModuleConfig['AllowsCC']);
						$xoopsTpl->assign("button_cancel",1);
						$xoopsTpl->assign("button_confirm_text","Payment via Credit Card");
						$xoopsTpl->assign("button_confirm_2",$xoopsModuleConfig['AllowsECH']);
						$xoopsTpl->assign("button_confirm_2_text","Payment via eCheck");
						$xoopsTpl->assign('confirm_url_2','?op=makepayment_echeck');
						$xoopsTpl->assign('confirm_action_2','makepayment_echeck');
						$xoopsTpl->assign('confirm_url','?op=makepayment');
						$xoopsTpl->assign("button_cancel_text","Cancel Payment");
						$xoopsTpl->assign('cancel_url','?op=cancel');
						$xoopsTpl->assign('confirm_action','makepayment');
					}
				} else {
					$xoopsTpl->assign("button_confirm",1);
					$xoopsTpl->assign("button_cancel",1);
					$xoopsTpl->assign("button_confirm_text","Confirm Payment");
					$xoopsTpl->assign('confirm_url','?op=confirm');
					$xoopsTpl->assign("button_cancel_text","Cancel Payment");
					$xoopsTpl->assign('cancel_url','?op=cancel');
					$xoopsTpl->assign('confirm_action','confirm');
				}								
				$items = getitemsonticket($ticket_id);
				$xoopsTpl->assign("purchase_array",$items);
				$xoopsTpl->assign('payment_total',$items[1]['total_aud']);
				$xoopsTpl->assign('payment_currency',"AUD");
				
			} else {
				$xoopsTpl->assign("button_confirm",0);
			}
	       break;

        case 'invoice' :
			
            $GLOBALS['xoopsOption']['template_main'] = 'makepayment_invoice.html';
			$user = validateuser($_GET['uid']);
			$ticket_id = validateticket($_SESSION['ticket'],$user);
			
			if ($ticket_id <>0){
				$xoopsTpl->assign("payment_to",$user['name']);
				$xoopsTpl->assign("xoops_pagetitle",'invoice number '.$ticket_id);
				$xoopsTpl->assign("button_confirm",$xoopsModuleConfig['AllowsCC']);
				$xoopsTpl->assign("button_cancel",1);
				$xoopsTpl->assign("button_confirm_text","Payment via Credit Card");
				$xoopsTpl->assign("button_confirm_2",$xoopsModuleConfig['AllowsECH']);
				$xoopsTpl->assign("button_confirm_2_text","Payment via eCheck");
				$xoopsTpl->assign('confirm_url_2','?op=makepayment_echeck');
				$xoopsTpl->assign('confirm_action_2','makepayment_echeck');
				$items = getitemsonticket($ticket_id);
				$xoopsTpl->assign("purchase_array",$items);
				$xoopsTpl->assign('payment_total',$items[1]['total_aud']);
				$xoopsTpl->assign('payment_currency',"AUD");
				$xoopsTpl->assign("button_cancel_text","Cancel Payment");
				$xoopsTpl->assign('confirm_url','?op=confirm');
				$xoopsTpl->assign('cancel_url','?op=cancel_back2merchant');
				$xoopsTpl->assign("button_cancel",1);			
			} else {
				$xoopsTpl->assign("button_confirm",0);
			}
	       break;
		case "goto_invoice":
			
			if (isset($_GET)) {
				foreach ( $_GET as $k => $v ) {
					${$k} = $v;
				}
			}
			if (isset($_POST)) {
				foreach ( $_POST as $k => $v ) {
					${$k} = $v;
				}
			} 				
			
			$_SESSION['ticket'] = $ticket;
			$_SESSION['uid'] = $uid;
			$sql = 'update _paythem_tickets set ticket_sessionid = \''.session_id().'\' where ticket_crc = \''.$ticket.'\'';
			$xoopsDB->query($sql);
			redirect_header('?op=invoice',0,'Redirecting you to invoice confirmation reciept');
		
		break;
		case "goto_displayinvoice":
			
			if (isset($_GET)) {
				foreach ( $_GET as $k => $v ) {
					${$k} = $v;
				}
			}
			if (isset($_POST)) {
				foreach ( $_POST as $k => $v ) {
					${$k} = $v;
				}
			} 				
			
			$_SESSION['ticket'] = $ticket;
			$_SESSION['uid'] = $uid;
			$sql = 'update _paythem_tickets set ticket_sessionid = \''.session_id().'\' where ticket_crc = \''.$ticket.'\'';
			$xoopsDB->query($sql);
			redirect_header('?op=displayinvoice',0,'Redirecting you to invoice confirmation reciept');
		
		break;
	}

require XOOPS_ROOT_PATH.'/footer.php';
?>
