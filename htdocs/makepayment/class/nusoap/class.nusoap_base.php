<?php

/*
$Id: class.nusoap_base.php,v 1.43 2005/08/04 01:27:42 snichol Exp $

NuSOAP - Web Services Toolkit for PHP

Copyright (c) 2002 NuSphere Corporation

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

If you have any questions or comments, please email:

Dietrich Ayala
dietrich@ganx4.com
http://dietrich.ganx4.com/nusoap

NuSphere Corporation
http://www.nusphere.com

*/

/* load classes

// necessary classes
require_once __DIR__ . '/class.soapclient.php';
require_once __DIR__ . '/class.soap_val.php';
require_once __DIR__ . '/class.soap_parser.php';
require_once __DIR__ . '/class.soap_fault.php';

// transport classes
require_once __DIR__ . '/class.soap_transport_http.php';

// optional add-on classes
require_once __DIR__ . '/class.xmlschema.php';
require_once __DIR__ . '/class.wsdl.php';

// server class
require_once __DIR__ . '/class.soap_server.php';*/

// class variable emulation
// cf. http://www.webkreator.com/php/techniques/php-static-class-variables.html
$GLOBALS['_transient']['static']['nusoap_base']->globalDebugLevel = 9;

/**
 * nusoap_base
 *
 * @author   Dietrich Ayala <dietrich@ganx4.com>
 * @version  $Id: class.nusoap_base.php,v 1.43 2005/08/04 01:27:42 snichol Exp $
 */
class nusoap_base
{
    /**
     * Identification for HTTP headers.
     *
     * @var string
     */
    public $title = 'NuSOAP';
    /**
     * Version for HTTP headers.
     *
     * @var string
     */
    public $version = '0.7.2';
    /**
     * CVS revision for HTTP headers.
     *
     * @var string
     */
    public $revision = '$Revision: 1.43 $';
    /**
     * Current error string (manipulated by getError/setError)
     *
     * @var string
     */
    public $error_str = '';
    /**
     * Current debug string (manipulated by debug/appendDebug/clearDebug/getDebug/getDebugAsXMLComment)
     *
     * @var string
     */
    public $debug_str = '';
    /**
     * toggles automatic encoding of special characters as entities
     * (should always be true, I think)
     *
     * @var boolean
     */
    public $charencoding = true;
    /**
     * the debug level for this instance
     *
     * @var	integer
     */
    public $debugLevel;

    /**
     * set schema version
     *
     * @var      string
     */
    public $XMLSchemaVersion = 'http://www.w3.org/2001/XMLSchema';

    /**
     * charset encoding for outgoing messages
     *
     * @var      string
     */
    public $soap_defencoding = 'ISO-8859-1';
    //var $soap_defencoding = 'UTF-8';

    /**
     * namespaces in an array of prefix => uri
     *
     * this is "seeded" by a set of constants, but it may be altered by code
     *
     * @var      array
     */
    public $namespaces = [
		'SOAP-ENV' => 'http://schemas.xmlsoap.org/soap/envelope/',
		'xsd' => 'http://www.w3.org/2001/XMLSchema',
		'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
		'SOAP-ENC' => 'http://schemas.xmlsoap.org/soap/encoding/',
		];

    /**
     * namespaces used in the current context, e.g. during serialization
     *
     * @var      array
     */
    public $usedNamespaces = [];

    /**
     * XML Schema types in an array of uri => (array of xml type => php type)
     * is this legacy yet?
     * no, this is used by the xmlschema class to verify type => namespace mappings.
     * @var      array
     */
    public $typemap = [
	'http://www.w3.org/2001/XMLSchema' => [
		'string' => 'string', 'boolean' => 'boolean', 'float' => 'double', 'double' => 'double', 'decimal' => 'double',
		'duration' => '', 'dateTime' => 'string', 'time' => 'string', 'date' => 'string', 'gYearMonth' => '',
		'gYear' => '', 'gMonthDay' => '', 'gDay' => '', 'gMonth' => '', 'hexBinary' => 'string', 'base64Binary' => 'string',
		// abstract "any" types
		'anyType' => 'string', 'anySimpleType' => 'string',
		// derived datatypes
		'normalizedString' => 'string', 'token' => 'string', 'language' => '', 'NMTOKEN' => '', 'NMTOKENS' => '', 'Name' => '', 'NCName' => '', 'ID' => '',
		'IDREF' => '', 'IDREFS' => '', 'ENTITY' => '', 'ENTITIES' => '', 'integer' => 'integer', 'nonPositiveInteger' => 'integer',
		'negativeInteger' => 'integer', 'long' => 'integer', 'int' => 'integer', 'short' => 'integer', 'byte' => 'integer', 'nonNegativeInteger' => 'integer',
		'unsignedLong' => '', 'unsignedInt' => '', 'unsignedShort' => '', 'unsignedByte' => '', 'positiveInteger' => '', ],
	'http://www.w3.org/2000/10/XMLSchema' => [
		'i4' => '', 'int' => 'integer', 'boolean' => 'boolean', 'string' => 'string', 'double' => 'double',
		'float' => 'double', 'dateTime' => 'string',
		'timeInstant' => 'string', 'base64Binary' => 'string', 'base64' => 'string', 'ur-type' => 'array', ],
	'http://www.w3.org/1999/XMLSchema' => [
		'i4' => '', 'int' => 'integer', 'boolean' => 'boolean', 'string' => 'string', 'double' => 'double',
		'float' => 'double', 'dateTime' => 'string',
		'timeInstant' => 'string', 'base64Binary' => 'string', 'base64' => 'string', 'ur-type' => 'array', ],
	'http://soapinterop.org/xsd' => ['SOAPStruct' => 'struct'],
	'http://schemas.xmlsoap.org/soap/encoding/' => ['base64' => 'string', 'array' => 'array', 'Array' => 'array'],
    'http://xml.apache.org/xml-soap' => ['Map'],
	];

    /**
     * XML entities to convert
     *
     * @var      array
     * @deprecated
     * @see	expandEntities
     */
    public $xmlEntities = ['quot' => '"', 'amp' => '&',
		'lt' => '<', 'gt' => '>', 'apos' => "'", ];

    /**
     * constructor
     */
    public function nusoap_base()
    {
        $this->debugLevel = $GLOBALS['_transient']['static']['nusoap_base']->globalDebugLevel;
    }

    /**
     * gets the global debug level, which applies to future instances
     *
     * @return	int	Debug level 0-9, where 0 turns off
     */
    public function getGlobalDebugLevel()
    {
        return $GLOBALS['_transient']['static']['nusoap_base']->globalDebugLevel;
    }

    /**
     * sets the global debug level, which applies to future instances
     *
     * @param	int	$level	Debug level 0-9, where 0 turns off
     */
    public function setGlobalDebugLevel($level)
    {
        $GLOBALS['_transient']['static']['nusoap_base']->globalDebugLevel = $level;
    }

    /**
     * gets the debug level for this instance
     *
     * @return	int	Debug level 0-9, where 0 turns off
     */
    public function getDebugLevel()
    {
        return $this->debugLevel;
    }

    /**
     * sets the debug level for this instance
     *
     * @param	int	$level	Debug level 0-9, where 0 turns off
     */
    public function setDebugLevel($level)
    {
        $this->debugLevel = $level;
    }

    /**
     * adds debug data to the instance debug string with formatting
     *
     * @param    string $string debug data
     */
    public function debug($string)
    {
        if ($this->debugLevel > 0) {
            $this->appendDebug($this->getmicrotime() . ' ' . get_class($this) . ": $string\n");
        }
    }

    /**
     * adds debug data to the instance debug string without formatting
     *
     * @param    string $string debug data
     */
    public function appendDebug($string)
    {
        if ($this->debugLevel > 0) {
            // it would be nice to use a memory stream here to use
            // memory more efficiently
            $this->debug_str .= $string;
        }
    }

    /**
     * clears the current debug data for this instance
     */
    public function clearDebug()
    {
        // it would be nice to use a memory stream here to use
        // memory more efficiently
        $this->debug_str = '';
    }

    /**
     * gets the current debug data for this instance
     *
     * @return   debug data
     */
    public function &getDebug()
    {
        // it would be nice to use a memory stream here to use
        // memory more efficiently
        return $this->debug_str;
    }

    /**
     * gets the current debug data for this instance as an XML comment
     * this may change the contents of the debug data
     *
     * @return   debug data as an XML comment
     */
    public function &getDebugAsXMLComment()
    {
        // it would be nice to use a memory stream here to use
        // memory more efficiently
        while (mb_strpos($this->debug_str, '--')) {
            $this->debug_str = str_replace('--', '- -', $this->debug_str);
        }

        return "<!--\n" . $this->debug_str . "\n-->";
    }

    /**
     * expands entities, e.g. changes '<' to '&lt;'.
     *
     * @param	string	$val	The string in which to expand entities.
     */
    public function expandEntities($val)
    {
        if ($this->charencoding) {
            $val = str_replace('&', '&amp;', $val);
            $val = str_replace("'", '&apos;', $val);
            $val = str_replace('"', '&quot;', $val);
            $val = str_replace('<', '&lt;', $val);
            $val = str_replace('>', '&gt;', $val);
        }

        return $val;
    }

    /**
     * returns error string if present
     *
     * @return   mixed error string or false
     */
    public function getError()
    {
        if ('' != $this->error_str) {
            return $this->error_str;
        }

        return false;
    }

    /**
     * sets error string
     *
     * @param mixed $str
     * @return   bool error string
     */
    public function setError($str)
    {
        $this->error_str = $str;
    }

    /**
     * detect if array is a simple array or a struct (associative array)
     *
     * @param	mixed	$val	The PHP array
     * @return	string	(arraySimple|arrayStruct)
     */
    public function isArraySimpleOrStruct($val)
    {
        $keyList = array_keys($val);
        foreach ($keyList as $keyListValue) {
            if (!is_int($keyListValue)) {
                return 'arrayStruct';
            }
        }

        return 'arraySimple';
    }

    /**
     * serializes PHP values in accordance w/ section 5. Type information is
     * not serialized if $use == 'literal'.
     *
     * @param	mixed	$val	The value to serialize
     * @param	string	$name	The name (local part) of the XML element
     * @param	string	$type	The XML schema type (local part) for the element
     * @param	string	$name_ns	The namespace for the name of the XML element
     * @param	string	$type_ns	The namespace for the type of the element
     * @param	array	$attributes	The attributes to serialize as name=>value pairs
     * @param	string	$use	The WSDL "use" (encoded|literal)
     * @return	string	The serialized element, possibly with child elements
     */
    public function serialize_val($val,$name = false,$type = false,$name_ns = false,$type_ns = false,$attributes = false,$use = 'encoded')
    {
        $this->debug("in serialize_val: name=$name, type=$type, name_ns=$name_ns, type_ns=$type_ns, use=$use");
        $this->appendDebug('value=' . $this->varDump($val));
        $this->appendDebug('attributes=' . $this->varDump($attributes));

        if (is_object($val) && 'soapval' == get_class($val)) {
            return $val->serialize($use);
        }
        // force valid name if necessary
        if (is_numeric($name)) {
            $name = '__numeric_' . $name;
        } elseif (!$name) {
            $name = 'noname';
        }
        // if name has ns, add ns prefix to name
        $xmlns = '';
        if ($name_ns) {
            $prefix = 'nu' . mt_rand(1000,9999);
            $name = $prefix . ':' . $name;
            $xmlns .= " xmlns:$prefix=\"$name_ns\"";
        }
        // if type is prefixed, create type prefix
        if ('' != $type_ns && $type_ns == $this->namespaces['xsd']) {
            // need to fix this. shouldn't default to xsd if no ns specified
            // w/o checking against typemap
            $type_prefix = 'xsd';
        } elseif ($type_ns) {
            $type_prefix = 'ns' . mt_rand(1000,9999);
            $xmlns .= " xmlns:$type_prefix=\"$type_ns\"";
        }
        // serialize attributes if present
        $atts = '';
        if ($attributes) {
            foreach ($attributes as $k => $v) {
                $atts .= " $k=\"" . $this->expandEntities($v) . '"';
            }
        }
        // serialize null value
        if (null === $val) {
            if ('literal' == $use) {
                // TODO: depends on minOccurs
                return "<$name$xmlns $atts>";
            }  
            if (isset($type) && isset($type_prefix)) {
                $type_str = " xsi:type=\"$type_prefix:$type\"";
            } else {
                $type_str = '';
            }

            return "<$name$xmlns$type_str $atts xsi:nil=\"true\">";
        }
        // serialize if an xsd built-in primitive type
        if ('' != $type && isset($this->typemap[$this->XMLSchemaVersion][$type])) {
            if (is_bool($val)) {
                if ('boolean' == $type) {
                    $val = $val ? 'true' : 'false';
                } elseif (!$val) {
                    $val = 0;
                }
            } else {
                if (is_string($val)) {
                    $val = $this->expandEntities($val);
                }
            }
            if ('literal' == $use) {
                return "<$name$xmlns $atts>$val</$name>";
            }
  
            return "<$name$xmlns $atts xsi:type=\"xsd:$type\">$val</$name>";
        }
        // detect type and serialize
        $xml = '';
        switch (true) {
			case (is_bool($val) || 'boolean' == $type):
        		if ('boolean' == $type) {
        		    $val = $val ? 'true' : 'false';
        		} elseif (!$val) {
        		    $val = 0;
        		}
				if ('literal' == $use) {
				    $xml .= "<$name$xmlns $atts>$val</$name>";
				} else {
				    $xml .= "<$name$xmlns xsi:type=\"xsd:boolean\"$atts>$val</$name>";
				}
				break;
			case (is_int($val) || is_int($val) || 'int' == $type):
				if ('literal' == $use) {
				    $xml .= "<$name$xmlns $atts>$val</$name>";
				} else {
				    $xml .= "<$name$xmlns xsi:type=\"xsd:int\"$atts>$val</$name>";
				}
				break;
			case (is_float($val) || is_float($val) || 'float' == $type):
				if ('literal' == $use) {
				    $xml .= "<$name$xmlns $atts>$val</$name>";
				} else {
				    $xml .= "<$name$xmlns xsi:type=\"xsd:float\"$atts>$val</$name>";
				}
				break;
			case (is_string($val) || 'string' == $type):
				$val = $this->expandEntities($val);
				if ('literal' == $use) {
				    $xml .= "<$name$xmlns $atts>$val</$name>";
				} else {
				    $xml .= "<$name$xmlns xsi:type=\"xsd:string\"$atts>$val</$name>";
				}
				break;
			case is_object($val):
				if (!$name) {
				    $name = get_class($val);
				    $this->debug("In serialize_val, used class name $name as element name");
				} else {
				    $this->debug("In serialize_val, do not override name $name for element name for class " . get_class($val));
				}
				foreach (get_object_vars($val) as $k => $v) {
				    $pXml = isset($pXml) ? $pXml . $this->serialize_val($v,$k,false,false,false,false,$use) : $this->serialize_val($v,$k,false,false,false,false,$use);
				}
				$xml .= '<' . $name . '>' . $pXml . '</' . $name . '>';
				break;
			break;
			case (is_array($val) || $type):
				// detect if struct or array
				$valueType = $this->isArraySimpleOrStruct($val);
                if ('arraySimple' == $valueType || ereg('^ArrayOf',$type)) {
                    $i = 0;
                    if (is_array($val) && count($val) > 0) {
                        foreach ($val as $v) {
                            if (is_object($v) && 'soapval' == get_class($v)) {
                                $tt_ns = $v->type_ns;
                                $tt = $v->type;
                            } elseif (is_array($v)) {
                                $tt = $this->isArraySimpleOrStruct($v);
                            } else {
                                $tt = gettype($v);
                            }
                            $array_types[$tt] = 1;
                            // TODO: for literal, the name should be $name
                            $xml .= $this->serialize_val($v,'item',false,false,false,false,$use);
                            ++$i;
                        }
                        if (count($array_types) > 1) {
                            $array_typename = 'xsd:anyType';
                        } elseif (isset($tt) && isset($this->typemap[$this->XMLSchemaVersion][$tt])) {
                            if ('integer' == $tt) {
                                $tt = 'int';
                            }
                            $array_typename = 'xsd:' . $tt;
                        } elseif (isset($tt) && 'arraySimple' == $tt) {
                            $array_typename = 'SOAP-ENC:Array';
                        } elseif (isset($tt) && 'arrayStruct' == $tt) {
                            $array_typename = 'unnamed_struct_use_soapval';
                        } else {
                            // if type is prefixed, create type prefix
                            if ('' != $tt_ns && $tt_ns == $this->namespaces['xsd']) {
                                $array_typename = 'xsd:' . $tt;
                            } elseif ($tt_ns) {
                                $tt_prefix = 'ns' . mt_rand(1000, 9999);
                                $array_typename = "$tt_prefix:$tt";
                                $xmlns .= " xmlns:$tt_prefix=\"$tt_ns\"";
                            } else {
                                $array_typename = $tt;
                            }
                        }
                        $array_type = $i;
                        if ('literal' == $use) {
                            $type_str = '';
                        } else {
                            if (isset($type) && isset($type_prefix)) {
                                $type_str = " xsi:type=\"$type_prefix:$type\"";
                            } else {
                                $type_str = ' xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="' . $array_typename . "[$array_type]\"";
                            }
                        }
                        // empty array
                    } else {
                        if ('literal' == $use) {
                            $type_str = '';
                        } else {
                            if (isset($type) && isset($type_prefix)) {
                                $type_str = " xsi:type=\"$type_prefix:$type\"";
                            } else {
                                $type_str = ' xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:anyType[0]"';
                            }
                        }
                    }
                    // TODO: for array in literal, there is no wrapper here
                    $xml = "<$name$xmlns$type_str$atts>" . $xml . "</$name>";
                } else {
                    // got a struct
                    if (isset($type) && isset($type_prefix)) {
                        $type_str = " xsi:type=\"$type_prefix:$type\"";
                    } else {
                        $type_str = '';
                    }
                    if ('literal' == $use) {
                        $xml .= "<$name$xmlns $atts>";
                    } else {
                        $xml .= "<$name$xmlns$type_str$atts>";
                    }
                    foreach ($val as $k => $v) {
                        // Apache Map
                        if ('Map' == $type && 'http://xml.apache.org/xml-soap' == $type_ns) {
                            $xml .= '<item>';
                            $xml .= $this->serialize_val($k,'key',false,false,false,false,$use);
                            $xml .= $this->serialize_val($v,'value',false,false,false,false,$use);
                            $xml .= '</item>';
                        } else {
                            $xml .= $this->serialize_val($v,$k,false,false,false,false,$use);
                        }
                    }
                    $xml .= "</$name>";
                }
				break;
			default:
				$xml .= 'not detected, got ' . gettype($val) . ' for ' . $val;
				break;
		}

        return $xml;
    }

    /**
     * serializes a message
     *
     * @param string $body the XML of the SOAP body
     * @param mixed $headers optional string of XML with SOAP header content, or array of soapval objects for SOAP headers
     * @param array $namespaces optional the namespaces used in generating the body and headers
     * @param string $style optional (rpc|document)
     * @param string $use optional (encoded|literal)
     * @param string $encodingStyle optional (usually 'http://schemas.xmlsoap.org/soap/encoding/' for encoded)
     * @return string the message
     */
    public function serializeEnvelope($body,$headers = false,$namespaces = [],$style = 'rpc',$use = 'encoded',$encodingStyle = 'http://schemas.xmlsoap.org/soap/encoding/')
    {
        // TODO: add an option to automatically run utf8_encode on $body and $headers
        // if $this->soap_defencoding is UTF-8.  Not doing this automatically allows
        // one to send arbitrary UTF-8 characters, not just characters that map to ISO-8859-1

        $this->debug('In serializeEnvelope length=' . mb_strlen($body) . ' body (max 1000 characters)=' . mb_substr($body, 0, 1000) . " style=$style use=$use encodingStyle=$encodingStyle");
        $this->debug('headers:');
        $this->appendDebug($this->varDump($headers));
        $this->debug('namespaces:');
        $this->appendDebug($this->varDump($namespaces));

        // serialize namespaces
        $ns_string = '';
        foreach (array_merge($this->namespaces,$namespaces) as $k => $v) {
            $ns_string .= " xmlns:$k=\"$v\"";
        }
        if ($encodingStyle) {
            $ns_string = " SOAP-ENV:encodingStyle=\"$encodingStyle\"$ns_string";
        }

        // serialize headers
        if ($headers) {
            if (is_array($headers)) {
                $xml = '';
                foreach ($headers as $header) {
                    $xml .= $this->serialize_val($header, false, false, false, false, false, $use);
                }
                $headers = $xml;
                $this->debug("In serializeEnvelope, serialzied array of headers to $headers");
            }
            $headers = '<SOAP-ENV:Header>' . $headers . '</SOAP-ENV:Header>';
        }
        // serialize envelope
        return
	'<?xml version="1.0" encoding="' . $this->soap_defencoding . '"?' . '>' .
	'<SOAP-ENV:Envelope' . $ns_string . '>' .
	$headers .
	'<SOAP-ENV:Body>' .
		$body .
	'</SOAP-ENV:Body>' .
	'</SOAP-ENV:Envelope>';
    }

    /**
     * formats a string to be inserted into an HTML stream
     *
     * @param string $str The string to format
     * @return string The formatted string
     * @deprecated
     */
    public function formatDump($str)
    {
        $str = htmlspecialchars($str);

        return nl2br($str);
    }

    /**
     * contracts (changes namespace to prefix) a qualified name
     *
     * @param    string $qname qname
     * @return	string contracted qname
     */
    public function contractQname($qname)
    {
        // get element namespace
        //$this->xdebug("Contract $qname");
        if (mb_strrpos($qname, ':')) {
            // get unqualified name
            $name = mb_substr($qname, mb_strrpos($qname, ':') + 1);
            // get ns
            $ns = mb_substr($qname, 0, mb_strrpos($qname, ':'));
            $p = $this->getPrefixFromNamespace($ns);
            if ($p) {
                return $p . ':' . $name;
            }

            return $qname;
        }
  
        return $qname;
    }

    /**
     * expands (changes prefix to namespace) a qualified name
     *
     * @param mixed $qname
     * @return	string expanded qname
     */
    public function expandQname($qname)
    {
        // get element prefix
        if (mb_strpos($qname,':') && !ereg('^http://',$qname)) {
            // get unqualified name
            $name = mb_substr(mb_strstr($qname,':'),1);
            // get ns prefix
            $prefix = mb_substr($qname,0,mb_strpos($qname,':'));
            if (isset($this->namespaces[$prefix])) {
                return $this->namespaces[$prefix] . ':' . $name;
            }
  
            return $qname;
        }
  
        return $qname;
    }

    /**
     * returns the local part of a prefixed string
     * returns the original string, if not prefixed
     *
     * @param string $str The prefixed string
     * @return string The local part
     */
    public function getLocalPart($str)
    {
        if ($sstr = mb_strrchr($str,':')) {
            // get unqualified name
            return mb_substr( $sstr, 1 );
        }
  
        return $str;
    }

    /**
     * returns the prefix part of a prefixed string
     * returns false, if not prefixed
     *
     * @param string $str The prefixed string
     * @return mixed The prefix or false if there is no prefix
     */
    public function getPrefix($str)
    {
        if ($pos = mb_strrpos($str,':')) {
            // get prefix
            return mb_substr($str,0,$pos);
        }

        return false;
    }

    /**
     * pass it a prefix, it returns a namespace
     *
     * @param string $prefix The prefix
     * @return mixed The namespace, false if no namespace has the specified prefix
     */
    public function getNamespaceFromPrefix($prefix)
    {
        if (isset($this->namespaces[$prefix])) {
            return $this->namespaces[$prefix];
        }
        //$this->setError("No namespace registered for prefix '$prefix'");
        return false;
    }

    /**
     * returns the prefix for a given namespace (or prefix)
     * or false if no prefixes registered for the given namespace
     *
     * @param string $ns The namespace
     * @return mixed The prefix, false if the namespace has no prefixes
     */
    public function getPrefixFromNamespace($ns)
    {
        foreach ($this->namespaces as $p => $n) {
            if ($ns == $n || $ns == $p) {
                $this->usedNamespaces[$p] = $n;

                return $p;
            }
        }

        return false;
    }

    /**
     * returns the time in ODBC canonical form with microseconds
     *
     * @return string The time in ODBC canonical form with microseconds
     */
    public function getmicrotime()
    {
        if (function_exists('gettimeofday')) {
            $tod = gettimeofday();
            $sec = $tod['sec'];
            $usec = $tod['usec'];
        } else {
            $sec = time();
            $usec = 0;
        }

        return strftime('%Y-%m-%d %H:%M:%S', $sec) . '.' . sprintf('%06d', $usec);
    }

    /**
     * Returns a string with the output of var_dump
     *
     * @param mixed $data The variable to var_dump
     * @return string The output of var_dump
     */
    public function varDump($data)
    {
        ob_start();
        var_dump($data);
        $ret_val = ob_get_contents();
        ob_end_clean();

        return $ret_val;
    }
}

// XML Schema Datatype Helper Functions

//xsd:dateTime helpers

/**
 * convert unix timestamp to ISO 8601 compliant date string
 *
 * @param    string $timestamp Unix time stamp
 * @param mixed $utc
 */
function timestamp_to_iso8601($timestamp,$utc = true)
{
    $datestr = date('Y-m-d\TH:i:sO',$timestamp);
    if ($utc) {
        $eregStr =
		'([0-9]{4})-' .	// centuries & years CCYY-
		'([0-9]{2})-' .	// months MM-
		'([0-9]{2})' .	// days DD
		'T' .			// separator T
		'([0-9]{2}):' .	// hours hh:
		'([0-9]{2}):' .	// minutes mm:
		'([0-9]{2})(\.[0-9]*)?' . // seconds ss.ss...
		'(Z|[+\-][0-9]{2}:?[0-9]{2})?'; // Z to indicate UTC, -/+HH:MM:SS.SS... for local tz's

		if (ereg($eregStr,$datestr,$regs)) {
		    return sprintf('%04d-%02d-%02dT%02d:%02d:%02dZ',$regs[1],$regs[2],$regs[3],$regs[4],$regs[5],$regs[6]);
		}

        return false;
    }
  
    return $datestr;
}

/**
 * convert ISO 8601 compliant date string to unix timestamp
 *
 * @param    string $datestr ISO 8601 compliant date string
 */
function iso8601_to_timestamp($datestr)
{
    $eregStr =
	'([0-9]{4})-' .	// centuries & years CCYY-
	'([0-9]{2})-' .	// months MM-
	'([0-9]{2})' .	// days DD
	'T' .			// separator T
	'([0-9]{2}):' .	// hours hh:
	'([0-9]{2}):' .	// minutes mm:
	'([0-9]{2})(\.[0-9]+)?' . // seconds ss.ss...
	'(Z|[+\-][0-9]{2}:?[0-9]{2})?'; // Z to indicate UTC, -/+HH:MM:SS.SS... for local tz's
	if (ereg($eregStr,$datestr,$regs)) {
	    // not utc
	    if ('Z' != $regs[8]) {
	        $op = mb_substr($regs[8],0,1);
	        $h = mb_substr($regs[8],1,2);
	        $m = mb_substr($regs[8],mb_strlen($regs[8]) - 2,2);
	        if ('-' == $op) {
	            $regs[4] = $regs[4] + $h;
	            $regs[5] = $regs[5] + $m;
	        } elseif ('+' == $op) {
	            $regs[4] = $regs[4] - $h;
	            $regs[5] = $regs[5] - $m;
	        }
	    }

	    return strtotime("$regs[1]-$regs[2]-$regs[3] $regs[4]:$regs[5]:$regs[6]Z");
	}
  
    return false;
}

/**
 * sleeps some number of microseconds
 *
 * @param    string $usec the number of microseconds to sleep
 * @deprecated
 */
function usleepWindows($usec)
{
    $start = gettimeofday();

    do {
        $stop = gettimeofday();
        $timePassed = 1000000 * ($stop['sec'] - $start['sec'])
		+ $stop['usec'] - $start['usec'];
    } while ($timePassed < $usec);
}

?>
