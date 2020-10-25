<?php

/**
 * transport class for sending/receiving data via HTTP and HTTPS
 * NOTE: PHP must be compiled with the CURL extension for HTTPS support
 *
 * @author   Dietrich Ayala <dietrich@ganx4.com>
 * @version  $Id: class.soap_transport_http.php,v 1.57 2005/07/27 19:24:42 snichol Exp $
 */
class soap_transport_http extends nusoap_base
{
    public $url = '';
    public $uri = '';
    public $digest_uri = '';
    public $scheme = '';
    public $host = '';
    public $port = '';
    public $path = '';
    public $request_method = 'POST';
    public $protocol_version = '1.0';
    public $encoding = '';
    public $outgoing_headers = [];
    public $incoming_headers = [];
    public $incoming_cookies = [];
    public $outgoing_payload = '';
    public $incoming_payload = '';
    public $useSOAPAction = true;
    public $persistentConnection = false;
    public $ch = false;	// cURL handle
    public $username = '';
    public $password = '';
    public $authtype = '';
    public $digestRequest = [];
    public $certRequest = [];	// keys must be cainfofile (optional), sslcertfile, sslkeyfile, passphrase, verifypeer (optional), verifyhost (optional)
    // cainfofile: certificate authority file, e.g. '$pathToPemFiles/rootca.pem'
    // sslcertfile: SSL certificate file, e.g. '$pathToPemFiles/mycert.pem'
    // sslkeyfile: SSL key file, e.g. '$pathToPemFiles/mykey.pem'
    // passphrase: SSL key password/passphrase
    // verifypeer: default is 1
    // verifyhost: default is 1

    /**
     * constructor
     * @param mixed $url
     */
    public function soap_transport_http($url)
    {
        parent::nusoap_base();
        $this->setURL($url);
        ereg('\$Revisio' . 'n: ([^ ]+)', $this->revision, $rev);
        $this->outgoing_headers['User-Agent'] = $this->title . '/' . $this->version . ' (' . $rev[1] . ')';
        $this->debug('set User-Agent: ' . $this->outgoing_headers['User-Agent']);
    }

    public function setURL($url)
    {
        $this->url = $url;

        $u = parse_url($url);
        foreach ($u as $k => $v) {
            $this->debug("$k = $v");
            $this->$k = $v;
        }

        // add any GET params to path
        if (isset($u['query']) && '' != $u['query']) {
            $this->path .= '?' . $u['query'];
        }

        // set default port
        if (!isset($u['port'])) {
            if ('https' == $u['scheme']) {
                $this->port = 443;
            } else {
                $this->port = 80;
            }
        }

        $this->uri = $this->path;
        $this->digest_uri = $this->uri;

        // build headers
        if (!isset($u['port'])) {
            $this->outgoing_headers['Host'] = $this->host;
        } else {
            $this->outgoing_headers['Host'] = $this->host . ':' . $this->port;
        }
        $this->debug('set Host: ' . $this->outgoing_headers['Host']);

        if (isset($u['user']) && '' != $u['user']) {
            $this->setCredentials(urldecode($u['user']), isset($u['pass']) ? urldecode($u['pass']) : '');
        }
    }

    public function connect($connection_timeout = 0,$response_timeout = 30)
    {
        // For PHP 4.3 with OpenSSL, change https scheme to ssl, then treat like
        // "regular" socket.
        // TODO: disabled for now because OpenSSL must be *compiled* in (not just
        //       loaded), and until PHP5 stream_get_wrappers is not available.
        //	  	if ($this->scheme == 'https') {
        //		  	if (version_compare(phpversion(), '4.3.0') >= 0) {
        //		  		if (extension_loaded('openssl')) {
        //		  			$this->scheme = 'ssl';
        //		  			$this->debug('Using SSL over OpenSSL');
        //		  		}
        //		  	}
        //		}
        $this->debug("connect connection_timeout $connection_timeout, response_timeout $response_timeout, scheme $this->scheme, host $this->host, port $this->port");
        if ('http' == $this->scheme || 'ssl' == $this->scheme) {
            // use persistent connection
            if ($this->persistentConnection && isset($this->fp) && is_resource($this->fp)) {
                if (!feof($this->fp)) {
                    $this->debug('Re-use persistent connection');

                    return true;
                }
                fclose($this->fp);
                $this->debug('Closed persistent connection at EOF');
            }

            // munge host if using OpenSSL
            if ('ssl' == $this->scheme) {
                $host = 'ssl://' . $this->host;
            } else {
                $host = $this->host;
            }
            $this->debug('calling fsockopen with host ' . $host . ' connection_timeout ' . $connection_timeout);

            // open socket
            if ($connection_timeout > 0) {
                $this->fp = @fsockopen( $host, $this->port, $this->errno, $this->error_str, $connection_timeout);
            } else {
                $this->fp = @fsockopen( $host, $this->port, $this->errno, $this->error_str);
            }

            // test pointer
            if (!$this->fp) {
                $msg = 'Couldn\'t open socket connection to server ' . $this->url;
                if ($this->errno) {
                    $msg .= ', Error (' . $this->errno . '): ' . $this->error_str;
                } else {
                    $msg .= ' prior to connect().  This is often a problem looking up the host name.';
                }
                $this->debug($msg);
                $this->setError($msg);

                return false;
            }

            // set response timeout
            $this->debug('set response timeout to ' . $response_timeout);
            socket_set_timeout( $this->fp, $response_timeout);

            $this->debug('socket connected');

            return true;
        } else {
            if ('https' == $this->scheme) {
                if (!extension_loaded('curl')) {
                    $this->setError('CURL Extension, or OpenSSL extension w/ PHP version >= 4.3 is required for HTTPS');

                    return false;
                }
                $this->debug('connect using https');
                // init CURL
                $this->ch = curl_init();
                // set url
                $hostURL = ('' != $this->port) ? "https://$this->host:$this->port" : "https://$this->host";
                // add path
                $hostURL .= $this->path;
                curl_setopt($this->ch, CURLOPT_URL, $hostURL);
                // follow location headers (re-directs)
                curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
                // ask for headers in the response output
                curl_setopt($this->ch, CURLOPT_HEADER, 1);
                // ask for the response output as the return value
                curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
                // encode
                // We manage this ourselves through headers and encoding
                //		if(function_exists('gzuncompress')){
                //			curl_setopt($this->ch, CURLOPT_ENCODING, 'deflate');
                //		}
                // persistent connection
                if ($this->persistentConnection) {
                    // The way we send data, we cannot use persistent connections, since
                    // there will be some "junk" at the end of our request.
                    //curl_setopt($this->ch, CURL_HTTP_VERSION_1_1, true);
                    $this->persistentConnection = false;
                    $this->outgoing_headers['Connection'] = 'close';
                    $this->debug('set Connection: ' . $this->outgoing_headers['Connection']);
                }
                // set timeout
                if (0 != $connection_timeout) {
                    curl_setopt($this->ch, CURLOPT_TIMEOUT, $connection_timeout);
                }
                // TODO: cURL has added a connection timeout separate from the response timeout
                //if ($connection_timeout != 0) {
                //	curl_setopt($this->ch, CURLOPT_CONNECTIONTIMEOUT, $connection_timeout);
                //}
                //if ($response_timeout != 0) {
                //	curl_setopt($this->ch, CURLOPT_TIMEOUT, $response_timeout);
                //}

                // recent versions of cURL turn on peer/host checking by default,
                // while PHP binaries are not compiled with a default location for the
                // CA cert bundle, so disable peer/host checking.
                //curl_setopt($this->ch, CURLOPT_CAINFO, 'f:\php-4.3.2-win32\extensions\curl-ca-bundle.crt');		
                curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);

                // support client certificates (thanks Tobias Boes, Doug Anarino, Eryan Ariobowo)
                if ('certificate' == $this->authtype) {
                    if (isset($this->certRequest['cainfofile'])) {
                        curl_setopt($this->ch, CURLOPT_CAINFO, $this->certRequest['cainfofile']);
                    }
                    if (isset($this->certRequest['verifypeer'])) {
                        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $this->certRequest['verifypeer']);
                    } else {
                        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 1);
                    }
                    if (isset($this->certRequest['verifyhost'])) {
                        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->certRequest['verifyhost']);
                    } else {
                        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 1);
                    }
                    if (isset($this->certRequest['sslcertfile'])) {
                        curl_setopt($this->ch, CURLOPT_SSLCERT, $this->certRequest['sslcertfile']);
                    }
                    if (isset($this->certRequest['sslkeyfile'])) {
                        curl_setopt($this->ch, CURLOPT_SSLKEY, $this->certRequest['sslkeyfile']);
                    }
                    if (isset($this->certRequest['passphrase'])) {
                        curl_setopt($this->ch, CURLOPT_SSLKEYPASSWD , $this->certRequest['passphrase']);
                    }
                }
                $this->debug('cURL connection set up');

                return true;
            }
        }  
        $this->setError('Unknown scheme ' . $this->scheme);
        $this->debug('Unknown scheme ' . $this->scheme);

        return false;
    }

    /**
     * send the SOAP message via HTTP
     *
     * @param    string $data message data
     * @param    int $timeout set connection timeout in seconds
     * @param	int $response_timeout set response timeout in seconds
     * @param	array $cookies cookies to send
     * @return	string data
     */
    public function send($data, $timeout = 0, $response_timeout = 30, $cookies = NULL)
    {
        $this->debug('entered send() with data of length: ' . mb_strlen($data));

        $this->tryagain = true;
        $tries = 0;
        while ($this->tryagain) {
            $this->tryagain = false;
            if ($tries++ < 2) {
                // make connnection
                if (!$this->connect($timeout, $response_timeout)) {
                    return false;
                }

                // send request
                if (!$this->sendRequest($data, $cookies)) {
                    return false;
                }

                // get response
                $respdata = $this->getResponse();
            } else {
                $this->setError('Too many tries to get an OK response');
            }
        }		
        $this->debug('end of send()');

        return $respdata;
    }

    /**
     * send the SOAP message via HTTPS 1.0 using CURL
     *
     * @param    int $timeout set connection timeout in seconds
     * @param	int $response_timeout set response timeout in seconds
     * @param	array $cookies cookies to send
     * @param mixed $data
     * @return	string data
     */
    public function sendHTTPS($data, $timeout, $response_timeout, $cookies)
    {
        return $this->send($data, $timeout, $response_timeout, $cookies);
    }

    /**
     * if authenticating, set user credentials here
     *
     * @param    string $username
     * @param    string $password
     * @param	string $authtype (basic, digest, certificate)
     * @param	array $digestRequest (keys must be nonce, nc, realm, qop)
     * @param	array $certRequest (keys must be cainfofile (optional), sslcertfile, sslkeyfile, passphrase, verifypeer (optional), verifyhost (optional): see corresponding options in cURL docs)
     */
    public function setCredentials($username, $password, $authtype = 'basic', $digestRequest = [], $certRequest = [])
    {
        $this->debug("Set credentials for authtype $authtype");
        // cf. RFC 2617
        if ('basic' == $authtype) {
            $this->outgoing_headers['Authorization'] = 'Basic ' . base64_encode(str_replace(':','',$username) . ':' . $password);
        } elseif ('digest' == $authtype) {
            if (isset($digestRequest['nonce'])) {
                $digestRequest['nc'] = isset($digestRequest['nc']) ? $digestRequest['nc']++ : 1;

                // calculate the Digest hashes (calculate code based on digest implementation found at: http://www.rassoc.com/gregr/weblog/stories/2002/07/09/webServicesSecurityHttpDigestAuthenticationWithoutActiveDirectory.html)

                // A1 = unq(username-value) ":" unq(realm-value) ":" passwd
                $A1 = $username . ':' . ($digestRequest['realm'] ?? '') . ':' . $password;

                // H(A1) = MD5(A1)
                $HA1 = md5($A1);

                // A2 = Method ":" digest-uri-value
                $A2 = 'POST:' . $this->digest_uri;

                // H(A2)
                $HA2 = md5($A2);

                // KD(secret, data) = H(concat(secret, ":", data))
                // if qop == auth:
                // request-digest  = <"> < KD ( H(A1),     unq(nonce-value)
                //                              ":" nc-value
                //                              ":" unq(cnonce-value)
                //                              ":" unq(qop-value)
                //                              ":" H(A2)
                //                            ) <">
                // if qop is missing,
                // request-digest  = <"> < KD ( H(A1), unq(nonce-value) ":" H(A2) ) > <">

                $unhashedDigest = '';
                $nonce = $digestRequest['nonce'] ?? '';
                $cnonce = $nonce;
                if ('' != $digestRequest['qop']) {
                    $unhashedDigest = $HA1 . ':' . $nonce . ':' . sprintf('%08d', $digestRequest['nc']) . ':' . $cnonce . ':' . $digestRequest['qop'] . ':' . $HA2;
                } else {
                    $unhashedDigest = $HA1 . ':' . $nonce . ':' . $HA2;
                }

                $hashedDigest = md5($unhashedDigest);

                $this->outgoing_headers['Authorization'] = 'Digest username="' . $username . '", realm="' . $digestRequest['realm'] . '", nonce="' . $nonce . '", uri="' . $this->digest_uri . '", cnonce="' . $cnonce . '", nc=' . sprintf('%08x', $digestRequest['nc']) . ', qop="' . $digestRequest['qop'] . '", response="' . $hashedDigest . '"';
            }
        } elseif ('certificate' == $authtype) {
            $this->certRequest = $certRequest;
        }
        $this->username = $username;
        $this->password = $password;
        $this->authtype = $authtype;
        $this->digestRequest = $digestRequest;

        if (isset($this->outgoing_headers['Authorization'])) {
            $this->debug('set Authorization: ' . mb_substr($this->outgoing_headers['Authorization'], 0, 12) . '...');
        } else {
            $this->debug('Authorization header not set');
        }
    }

    /**
     * set the soapaction value
     *
     * @param    string $soapaction
     */
    public function setSOAPAction($soapaction)
    {
        $this->outgoing_headers['SOAPAction'] = '"' . $soapaction . '"';
        $this->debug('set SOAPAction: ' . $this->outgoing_headers['SOAPAction']);
    }

    /**
     * use http encoding
     *
     * @param    string $enc encoding style. supported values: gzip, deflate, or both
     */
    public function setEncoding($enc = 'gzip, deflate')
    {
        if (function_exists('gzdeflate')) {
            $this->protocol_version = '1.1';
            $this->outgoing_headers['Accept-Encoding'] = $enc;
            $this->debug('set Accept-Encoding: ' . $this->outgoing_headers['Accept-Encoding']);
            if (!isset($this->outgoing_headers['Connection'])) {
                $this->outgoing_headers['Connection'] = 'close';
                $this->persistentConnection = false;
                $this->debug('set Connection: ' . $this->outgoing_headers['Connection']);
            }
            set_magic_quotes_runtime(0);
            // deprecated
            $this->encoding = $enc;
        }
    }

    /**
     * set proxy info here
     *
     * @param    string $proxyhost
     * @param    string $proxyport
     * @param	string $proxyusername
     * @param	string $proxypassword
     */
    public function setProxy($proxyhost, $proxyport, $proxyusername = '', $proxypassword = '')
    {
        $this->uri = $this->url;
        $this->host = $proxyhost;
        $this->port = $proxyport;
        if ('' != $proxyusername && '' != $proxypassword) {
            $this->outgoing_headers['Proxy-Authorization'] = ' Basic ' . base64_encode($proxyusername . ':' . $proxypassword);
            $this->debug('set Proxy-Authorization: ' . $this->outgoing_headers['Proxy-Authorization']);
        }
    }

    /**
     * decode a string that is encoded w/ "chunked' transfer encoding
     * as defined in RFC2068 19.4.6
     *
     * @param    string $buffer
     * @param    string $lb
     * @returns	string
     * @deprecated
     */
    public function decodeChunked($buffer, $lb)
    {
        // length := 0
        $length = 0;
        $new = '';

        // read chunk-size, chunk-extension (if any) and CRLF
        // get the position of the linebreak
        $chunkend = mb_strpos($buffer, $lb);
        if (false === $chunkend) {
            $this->debug('no linebreak found in decodeChunked');

            return $new;
        }
        $temp = mb_substr($buffer,0,$chunkend);
        $chunk_size = hexdec( trim($temp) );
        $chunkstart = $chunkend + mb_strlen($lb);
        // while (chunk-size > 0) {
        while ($chunk_size > 0) {
            $this->debug("chunkstart: $chunkstart chunk_size: $chunk_size");
            $chunkend = mb_strpos( $buffer, $lb, $chunkstart + $chunk_size);

            // Just in case we got a broken connection
            if (false === $chunkend) {
                $chunk = mb_substr($buffer,$chunkstart);
                // append chunk-data to entity-body
                $new .= $chunk;
                $length += mb_strlen($chunk);
                break;
            }

            // read chunk-data and CRLF
            $chunk = mb_substr($buffer,$chunkstart,$chunkend - $chunkstart);
            // append chunk-data to entity-body
            $new .= $chunk;
            // length := length + chunk-size
            $length += mb_strlen($chunk);
            // read chunk-size and CRLF
            $chunkstart = $chunkend + mb_strlen($lb);

            $chunkend = mb_strpos($buffer, $lb, $chunkstart) + mb_strlen($lb);
            if (false === $chunkend) {
                break; //Just in case we got a broken connection
            }
            $temp = mb_substr($buffer,$chunkstart,$chunkend - $chunkstart);
            $chunk_size = hexdec( trim($temp) );
            $chunkstart = $chunkend;
        }

        return $new;
    }

    /*
     *	Writes payload, including HTTP headers, to $this->outgoing_payload.
     */
    public function buildPayload($data, $cookie_str = '')
    {
        // add content-length header
        $this->outgoing_headers['Content-Length'] = mb_strlen($data);
        $this->debug('set Content-Length: ' . $this->outgoing_headers['Content-Length']);

        // start building outgoing payload:
        $req = "$this->request_method $this->uri HTTP/$this->protocol_version";
        $this->debug("HTTP request: $req");
        $this->outgoing_payload = "$req\r\n";

        // loop thru headers, serializing
        foreach ($this->outgoing_headers as $k => $v) {
            $hdr = $k . ': ' . $v;
            $this->debug("HTTP header: $hdr");
            $this->outgoing_payload .= "$hdr\r\n";
        }

        // add any cookies
        if ('' != $cookie_str) {
            $hdr = 'Cookie: ' . $cookie_str;
            $this->debug("HTTP header: $hdr");
            $this->outgoing_payload .= "$hdr\r\n";
        }

        // header/body separator
        $this->outgoing_payload .= "\r\n";

        // add data
        $this->outgoing_payload .= $data;
    }

    public function sendRequest($data, $cookies = NULL)
    {
        // build cookie string
        $cookie_str = $this->getCookiesForRequest($cookies, (('ssl' == $this->scheme) || ('https' == $this->scheme)));

        // build payload
        $this->buildPayload($data, $cookie_str);

        if ('http' == $this->scheme || 'ssl' == $this->scheme) {
            // send payload
            if (!fwrite($this->fp, $this->outgoing_payload, mb_strlen($this->outgoing_payload))) {
                $this->setError('couldn\'t write message data to socket');
                $this->debug('couldn\'t write message data to socket');

                return false;
            }
            $this->debug('wrote data to socket, length = ' . mb_strlen($this->outgoing_payload));

            return true;
        } else {
            if ('https' == $this->scheme) {
                // set payload
                // TODO: cURL does say this should only be the verb, and in fact it
                // turns out that the URI and HTTP version are appended to this, which
                // some servers refuse to work with
                //curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->outgoing_payload);
                foreach ($this->outgoing_headers as $k => $v) {
                    $curl_headers[] = "$k: $v";
                }
                if ('' != $cookie_str) {
                    $curl_headers[] = 'Cookie: ' . $cookie_str;
                }
                curl_setopt($this->ch, CURLOPT_HTTPHEADER, $curl_headers);
                if ('POST' == $this->request_method) {
                    curl_setopt($this->ch, CURLOPT_POST, 1);
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
                }  

                $this->debug('set cURL payload');

                return true;
            }
        }
    }

    public function getResponse()
    {
        $this->incoming_payload = '';

        if ('http' == $this->scheme || 'ssl' == $this->scheme) {
            // loop until headers have been retrieved
            $data = '';
            while (!isset($lb)) {
                // We might EOF during header read.
                if (feof($this->fp)) {
                    $this->incoming_payload = $data;
                    $this->debug('found no headers before EOF after length ' . mb_strlen($data));
                    $this->debug("received before EOF:\n" . $data);
                    $this->setError('server failed to send headers');

                    return false;
                }

                $tmp = fgets($this->fp, 256);
                $tmplen = mb_strlen($tmp);
                $this->debug("read line of $tmplen bytes: " . trim($tmp));

                if (0 == $tmplen) {
                    $this->incoming_payload = $data;
                    $this->debug('socket read of headers timed out after length ' . mb_strlen($data));
                    $this->debug('read before timeout: ' . $data);
                    $this->setError('socket read of headers timed out');

                    return false;
                }

                $data .= $tmp;
                $pos = mb_strpos($data,"\r\n\r\n");
                if ($pos > 1) {
                    $lb = "\r\n";
                } else {
                    $pos = mb_strpos($data,"\n\n");
                    if ($pos > 1) {
                        $lb = "\n";
                    }
                }
                // remove 100 header
                if (isset($lb) && ereg('^HTTP/1.1 100',$data)) {
                    unset($lb);
                    $data = '';
                }
            }
            // store header data
            $this->incoming_payload .= $data;
            $this->debug('found end of headers after length ' . mb_strlen($data));
            // process headers
            $header_data = trim(mb_substr($data,0,$pos));
            $header_array = explode($lb,$header_data);
            $this->incoming_headers = [];
            $this->incoming_cookies = [];
            foreach ($header_array as $header_line) {
                $arr = explode(':',$header_line, 2);
                if (count($arr) > 1) {
                    $header_name = mb_strtolower(trim($arr[0]));
                    $this->incoming_headers[$header_name] = trim($arr[1]);
                    if ('set-cookie' == $header_name) {
                        // TODO: allow multiple cookies from parseCookie
                        $cookie = $this->parseCookie(trim($arr[1]));
                        if ($cookie) {
                            $this->incoming_cookies[] = $cookie;
                            $this->debug('found cookie: ' . $cookie['name'] . ' = ' . $cookie['value']);
                        } else {
                            $this->debug('did not find cookie in ' . trim($arr[1]));
                        }
                    }
                } else {
                    if (isset($header_name)) {
                        // append continuation line to previous header
                        $this->incoming_headers[$header_name] .= $lb . ' ' . $header_line;
                    }
                }
            }

            // loop until msg has been received
            if (isset($this->incoming_headers['transfer-encoding']) && 'chunked' == mb_strtolower($this->incoming_headers['transfer-encoding'])) {
                $content_length = 2147483647;	// ignore any content-length header
                $chunked = true;
                $this->debug('want to read chunked content');
            } elseif (isset($this->incoming_headers['content-length'])) {
                $content_length = $this->incoming_headers['content-length'];
                $chunked = false;
                $this->debug("want to read content of length $content_length");
            } else {
                $content_length = 2147483647;
                $chunked = false;
                $this->debug('want to read content to EOF');
            }
            $data = '';
            do {
                if ($chunked) {
                    $tmp = fgets($this->fp, 256);
                    $tmplen = mb_strlen($tmp);
                    $this->debug("read chunk line of $tmplen bytes");
                    if (0 == $tmplen) {
                        $this->incoming_payload = $data;
                        $this->debug('socket read of chunk length timed out after length ' . mb_strlen($data));
                        $this->debug("read before timeout:\n" . $data);
                        $this->setError('socket read of chunk length timed out');

                        return false;
                    }
                    $content_length = hexdec(trim($tmp));
                    $this->debug("chunk length $content_length");
                }
                $strlen = 0;
                while (($strlen < $content_length) && (!feof($this->fp))) {
                    $readlen = min(8192, $content_length - $strlen);
                    $tmp = fread($this->fp, $readlen);
                    $tmplen = mb_strlen($tmp);
                    $this->debug("read buffer of $tmplen bytes");
                    if ((0 == $tmplen) && (!feof($this->fp))) {
                        $this->incoming_payload = $data;
                        $this->debug('socket read of body timed out after length ' . mb_strlen($data));
                        $this->debug("read before timeout:\n" . $data);
                        $this->setError('socket read of body timed out');

                        return false;
                    }
                    $strlen += $tmplen;
                    $data .= $tmp;
                }
                if ($chunked && ($content_length > 0)) {
                    $tmp = fgets($this->fp, 256);
                    $tmplen = mb_strlen($tmp);
                    $this->debug("read chunk terminator of $tmplen bytes");
                    if (0 == $tmplen) {
                        $this->incoming_payload = $data;
                        $this->debug('socket read of chunk terminator timed out after length ' . mb_strlen($data));
                        $this->debug("read before timeout:\n" . $data);
                        $this->setError('socket read of chunk terminator timed out');

                        return false;
                    }
                }
            } while ($chunked && ($content_length > 0) && (!feof($this->fp)));
            if (feof($this->fp)) {
                $this->debug('read to EOF');
            }
            $this->debug('read body of length ' . mb_strlen($data));
            $this->incoming_payload .= $data;
            $this->debug('received a total of ' . mb_strlen($this->incoming_payload) . ' bytes of data from server');

            // close filepointer
            if (
			(isset($this->incoming_headers['connection']) && 'close' == mb_strtolower($this->incoming_headers['connection'])) ||
			(!$this->persistentConnection) || feof($this->fp)) {
                fclose($this->fp);
                $this->fp = false;
                $this->debug('closed socket');
            }

            // connection was closed unexpectedly
            if ('' == $this->incoming_payload) {
                $this->setError('no response from server');

                return false;
            }

            // decode transfer-encoding
//		if(isset($this->incoming_headers['transfer-encoding']) && strtolower($this->incoming_headers['transfer-encoding']) == 'chunked'){
//			if(!$data = $this->decodeChunked($data, $lb)){
//				$this->setError('Decoding of chunked data failed');
//				return false;
//			}
			//print "<pre>\nde-chunked:\n---------------\n$data\n\n---------------\n</pre>";
			// set decoded payload
//			$this->incoming_payload = $header_data.$lb.$lb.$data;
//		}
        } else {
            if ('https' == $this->scheme) {
                // send and receive
                $this->debug('send and receive with cURL');
                $this->incoming_payload = curl_exec($this->ch);
                $data = $this->incoming_payload;

                $cErr = curl_error($this->ch);
                if ('' != $cErr) {
                    $err = 'cURL ERROR: ' . curl_errno($this->ch) . ': ' . $cErr . '<br>';
                    // TODO: there is a PHP bug that can cause this to SEGV for CURLINFO_CONTENT_TYPE
                    foreach (curl_getinfo($this->ch) as $k => $v) {
                        $err .= "$k: $v<br>";
                    }
                    $this->debug($err);
                    $this->setError($err);
                    curl_close($this->ch);

                    return false;
                }  
                //echo '<pre>';
                //var_dump(curl_getinfo($this->ch));
                //echo '</pre>';

                // close curl
                $this->debug('No cURL error, closing cURL');
                curl_close($this->ch);

                // remove 100 header(s)
                while (ereg('^HTTP/1.1 100',$data)) {
                    if ($pos = mb_strpos($data,"\r\n\r\n")) {
                        $data = ltrim(mb_substr($data,$pos));
                    } elseif ($pos = mb_strpos($data,"\n\n") ) {
                        $data = ltrim(mb_substr($data,$pos));
                    }
                }

                // separate content from HTTP headers
                if ($pos = mb_strpos($data,"\r\n\r\n")) {
                    $lb = "\r\n";
                } elseif ( $pos = mb_strpos($data,"\n\n")) {
                    $lb = "\n";
                } else {
                    $this->debug('no proper separation of headers and document');
                    $this->setError('no proper separation of headers and document');

                    return false;
                }
                $header_data = trim(mb_substr($data,0,$pos));
                $header_array = explode($lb,$header_data);
                $data = ltrim(mb_substr($data,$pos));
                $this->debug('found proper separation of headers and document');
                $this->debug('cleaned data, stringlen: ' . mb_strlen($data));
                // clean headers
                foreach ($header_array as $header_line) {
                    $arr = explode(':',$header_line,2);
                    if (count($arr) > 1) {
                        $header_name = mb_strtolower(trim($arr[0]));
                        $this->incoming_headers[$header_name] = trim($arr[1]);
                        if ('set-cookie' == $header_name) {
                            // TODO: allow multiple cookies from parseCookie
                            $cookie = $this->parseCookie(trim($arr[1]));
                            if ($cookie) {
                                $this->incoming_cookies[] = $cookie;
                                $this->debug('found cookie: ' . $cookie['name'] . ' = ' . $cookie['value']);
                            } else {
                                $this->debug('did not find cookie in ' . trim($arr[1]));
                            }
                        }
                    } else {
                        if (isset($header_name)) {
                            // append continuation line to previous header
                            $this->incoming_headers[$header_name] .= $lb . ' ' . $header_line;
                        }
                    }
                }
            }
        }

        $arr = explode(' ', $header_array[0], 3);
        $http_version = $arr[0];
        $http_status = intval($arr[1]);
        $http_reason = count($arr) > 2 ? $arr[2] : '';

        // see if we need to resend the request with http digest authentication
        if (isset($this->incoming_headers['location']) && 301 == $http_status) {
            $this->debug("Got 301 $http_reason with Location: " . $this->incoming_headers['location']);
            $this->setURL($this->incoming_headers['location']);
            $this->tryagain = true;

            return false;
        }

        // see if we need to resend the request with http digest authentication
        if (isset($this->incoming_headers['www-authenticate']) && 401 == $http_status) {
            $this->debug("Got 401 $http_reason with WWW-Authenticate: " . $this->incoming_headers['www-authenticate']);
            if (mb_strstr($this->incoming_headers['www-authenticate'], 'Digest ')) {
                $this->debug('Server wants digest authentication');
                // remove "Digest " from our elements
                $digestString = str_replace('Digest ', '', $this->incoming_headers['www-authenticate']);

                // parse elements into array
                $digestElements = explode(',', $digestString);
                foreach ($digestElements as $val) {
                    $tempElement = explode('=', trim($val), 2);
                    $digestRequest[$tempElement[0]] = str_replace('"', '', $tempElement[1]);
                }

                // should have (at least) qop, realm, nonce
                if (isset($digestRequest['nonce'])) {
                    $this->setCredentials($this->username, $this->password, 'digest', $digestRequest);
                    $this->tryagain = true;

                    return false;
                }
            }
            $this->debug('HTTP authentication failed');
            $this->setError('HTTP authentication failed');

            return false;
        }

        if (
			($http_status >= 300 && $http_status <= 307) ||
			($http_status >= 400 && $http_status <= 417) ||
			($http_status >= 501 && $http_status <= 505)
		   ) {
            $this->setError("Unsupported HTTP response status $http_status $http_reason (soapclient->response has contents of the response)");

            return false;
        }

        // decode content-encoding
        if (isset($this->incoming_headers['content-encoding']) && '' != $this->incoming_headers['content-encoding']) {
            if ('deflate' == mb_strtolower($this->incoming_headers['content-encoding']) || 'gzip' == mb_strtolower($this->incoming_headers['content-encoding'])) {
                // if decoding works, use it. else assume data wasn't gzencoded
                if (function_exists('gzinflate')) {
                    //$timer->setMarker('starting decoding of gzip/deflated content');
                    // IIS 5 requires gzinflate instead of gzuncompress (similar to IE 5 and gzdeflate v. gzcompress)
                    // this means there are no Zlib headers, although there should be
                    $this->debug('The gzinflate function exists');
                    $datalen = mb_strlen($data);
                    if ('deflate' == $this->incoming_headers['content-encoding']) {
                        if ($degzdata = @gzinflate($data)) {
                            $data = $degzdata;
                            $this->debug('The payload has been inflated to ' . mb_strlen($data) . ' bytes');
                            if (mb_strlen($data) < $datalen) {
                                // test for the case that the payload has been compressed twice
                                $this->debug('The inflated payload is smaller than the gzipped one; try again');
                                if ($degzdata = @gzinflate($data)) {
                                    $data = $degzdata;
                                    $this->debug('The payload has been inflated again to ' . mb_strlen($data) . ' bytes');
                                }
                            }
                        } else {
                            $this->debug('Error using gzinflate to inflate the payload');
                            $this->setError('Error using gzinflate to inflate the payload');
                        }
                    } elseif ('gzip' == $this->incoming_headers['content-encoding']) {
                        if ($degzdata = @gzinflate(mb_substr($data, 10))) {	// do our best
                            $data = $degzdata;
                            $this->debug('The payload has been un-gzipped to ' . mb_strlen($data) . ' bytes');
                            if (mb_strlen($data) < $datalen) {
                                // test for the case that the payload has been compressed twice
                                $this->debug('The un-gzipped payload is smaller than the gzipped one; try again');
                                if ($degzdata = @gzinflate(mb_substr($data, 10))) {
                                    $data = $degzdata;
                                    $this->debug('The payload has been un-gzipped again to ' . mb_strlen($data) . ' bytes');
                                }
                            }
                        } else {
                            $this->debug('Error using gzinflate to un-gzip the payload');
                            $this->setError('Error using gzinflate to un-gzip the payload');
                        }
                    }
                    //$timer->setMarker('finished decoding of gzip/deflated content');
                    //print "<xmp>\nde-inflated:\n---------------\n$data\n-------------\n</xmp>";
                    // set decoded payload
                    $this->incoming_payload = $header_data . $lb . $lb . $data;
                } else {
                    $this->debug('The server sent compressed data. Your php install must have the Zlib extension compiled in to support this.');
                    $this->setError('The server sent compressed data. Your php install must have the Zlib extension compiled in to support this.');
                }
            } else {
                $this->debug('Unsupported Content-Encoding ' . $this->incoming_headers['content-encoding']);
                $this->setError('Unsupported Content-Encoding ' . $this->incoming_headers['content-encoding']);
            }
        } else {
            $this->debug('No Content-Encoding header');
        }

        if (0 == mb_strlen($data)) {
            $this->debug('no data after headers!');
            $this->setError('no data present after HTTP headers');

            return false;
        }

        return $data;
    }

    public function setContentType($type, $charset = false)
    {
        $this->outgoing_headers['Content-Type'] = $type . ($charset ? '; charset=' . $charset : '');
        $this->debug('set Content-Type: ' . $this->outgoing_headers['Content-Type']);
    }

    public function usePersistentConnection()
    {
        if (isset($this->outgoing_headers['Accept-Encoding'])) {
            return false;
        }
        $this->protocol_version = '1.1';
        $this->persistentConnection = true;
        $this->outgoing_headers['Connection'] = 'Keep-Alive';
        $this->debug('set Connection: ' . $this->outgoing_headers['Connection']);

        return true;
    }

    /**
     * parse an incoming Cookie into it's parts
     *
     * @param	string $cookie_str content of cookie
     * @return	array with data of that cookie
     */
    /*
     * TODO: allow a Set-Cookie string to be parsed into multiple cookies
     */
    public function parseCookie($cookie_str)
    {
        $cookie_str = str_replace('; ', ';', $cookie_str) . ';';
        $data = split(';', $cookie_str);
        $value_str = $data[0];

        $cookie_param = 'domain=';
        $start = mb_strpos($cookie_str, $cookie_param);
        if ($start > 0) {
            $domain = mb_substr($cookie_str, $start + mb_strlen($cookie_param));
            $domain = mb_substr($domain, 0, mb_strpos($domain, ';'));
        } else {
            $domain = '';
        }

        $cookie_param = 'expires=';
        $start = mb_strpos($cookie_str, $cookie_param);
        if ($start > 0) {
            $expires = mb_substr($cookie_str, $start + mb_strlen($cookie_param));
            $expires = mb_substr($expires, 0, mb_strpos($expires, ';'));
        } else {
            $expires = '';
        }

        $cookie_param = 'path=';
        $start = mb_strpos($cookie_str, $cookie_param);
        if ( $start > 0 ) {
            $path = mb_substr($cookie_str, $start + mb_strlen($cookie_param));
            $path = mb_substr($path, 0, mb_strpos($path, ';'));
        } else {
            $path = '/';
        }

        $cookie_param = ';secure;';
        if (FALSE !== mb_strpos($cookie_str, $cookie_param)) {
            $secure = true;
        } else {
            $secure = false;
        }

        $sep_pos = mb_strpos($value_str, '=');

        if ($sep_pos) {
            $name = mb_substr($value_str, 0, $sep_pos);
            $value = mb_substr($value_str, $sep_pos + 1);
            $cookie = [	'name' => $name,
			                'value' => $value,
							'domain' => $domain,
							'path' => $path,
							'expires' => $expires,
							'secure' => $secure,
							];
		
            return $cookie;
        }

        return false;
    }

    /**
     * sort out cookies for the current request
     *
     * @param	array $cookies array with all cookies
     * @param	bool $secure is the send-content secure or not?
     * @return	string for Cookie-HTTP-Header
     */
    public function getCookiesForRequest($cookies, $secure = false)
    {
        $cookie_str = '';
        if ((null !== $cookies) && (is_array($cookies))) {
            foreach ($cookies as $cookie) {
                if (!is_array($cookie)) {
                    continue;
                }
                $this->debug('check cookie for validity: ' . $cookie['name'] . '=' . $cookie['value']);
                if ((isset($cookie['expires'])) && (!empty($cookie['expires']))) {
                    if (strtotime($cookie['expires']) <= time()) {
                        $this->debug('cookie has expired');
                        continue;
                    }
                }
                if ((isset($cookie['domain'])) && (!empty($cookie['domain']))) {
                    $domain = preg_quote($cookie['domain']);
                    if (!preg_match("'.*$domain$'i", $this->host)) {
                        $this->debug('cookie has different domain');
                        continue;
                    }
                }
                if ((isset($cookie['path'])) && (!empty($cookie['path']))) {
                    $path = preg_quote($cookie['path']);
                    if (!preg_match("'^$path.*'i", $this->path)) {
                        $this->debug('cookie is for a different path');
                        continue;
                    }
                }
                if ((!$secure) && (isset($cookie['secure'])) && ($cookie['secure'])) {
                    $this->debug('cookie is secure, transport is not');
                    continue;
                }
                $cookie_str .= $cookie['name'] . '=' . $cookie['value'] . '; ';
                $this->debug('add cookie to Cookie-String: ' . $cookie['name'] . '=' . $cookie['value']);
            }
        }

        return $cookie_str;
    }
}

?>
