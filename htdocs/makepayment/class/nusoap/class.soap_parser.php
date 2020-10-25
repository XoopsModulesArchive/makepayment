<?php

/**
 * soap_parser class parses SOAP XML messages into native PHP values
 *
 * @author   Dietrich Ayala <dietrich@ganx4.com>
 * @version  $Id: class.soap_parser.php,v 1.36 2005/08/04 01:27:42 snichol Exp $
 */
class soap_parser extends nusoap_base
{
    public $xml = '';
    public $xml_encoding = '';
    public $method = '';
    public $root_struct = '';
    public $root_struct_name = '';
    public $root_struct_namespace = '';
    public $root_header = '';
    public $document = '';			// incoming SOAP body (text)
    // determines where in the message we are (envelope,header,body,method)
    public $status = '';
    public $position = 0;
    public $depth = 0;
    public $default_namespace = '';
    public $namespaces = [];
    public $message = [];
    public $parent = '';
    public $fault = false;
    public $fault_code = '';
    public $fault_str = '';
    public $fault_detail = '';
    public $depth_array = [];
    public $debug_flag = true;
    public $soapresponse = NULL;
    public $responseHeaders = '';	// incoming SOAP headers (text)
    public $body_position = 0;
    // for multiref parsing:
    // array of id => pos
    public $ids = [];
    // array of id => hrefs => pos
    public $multirefs = [];
    // toggle for auto-decoding element content
    public $decode_utf8 = true;

    /**
     * constructor that actually does the parsing
     *
     * @param    string $xml SOAP message
     * @param    string $encoding character encoding scheme of message
     * @param    string $method method for which XML is parsed (unused?)
     * @param    string $decode_utf8 whether to decode UTF-8 to ISO-8859-1
     */
    public function soap_parser($xml,$encoding = 'UTF-8',$method = '',$decode_utf8 = true)
    {
        parent::nusoap_base();
        $this->xml = $xml;
        $this->xml_encoding = $encoding;
        $this->method = $method;
        $this->decode_utf8 = $decode_utf8;

        // Check whether content has been read.
        if (!empty($xml)) {
            // Check XML encoding
            $pos_xml = mb_strpos($xml, '<?xml');
            if (FALSE !== $pos_xml) {
                $xml_decl = mb_substr($xml, $pos_xml, mb_strpos($xml, '?>', $pos_xml + 2) - $pos_xml + 1);
                if (preg_match("/encoding=[\"']([^\"']*)[\"']/", $xml_decl, $res)) {
                    $xml_encoding = $res[1];
                    if (mb_strtoupper($xml_encoding) != $encoding) {
                        $err = "Charset from HTTP Content-Type '" . $encoding . "' does not match encoding from XML declaration '" . $xml_encoding . "'";
                        $this->debug($err);
                        if ('ISO-8859-1' != $encoding || 'UTF-8' != mb_strtoupper($xml_encoding)) {
                            $this->setError($err);

                            return;
                        }
                        // when HTTP says ISO-8859-1 (the default) and XML says UTF-8 (the typical), assume the other endpoint is just sloppy and proceed
                    } else {
                        $this->debug('Charset from HTTP Content-Type matches encoding from XML declaration');
                    }
                } else {
                    $this->debug('No encoding specified in XML declaration');
                }
            } else {
                $this->debug('No XML declaration');
            }
            $this->debug('Entering soap_parser(), length=' . mb_strlen($xml) . ', encoding=' . $encoding);
            // Create an XML parser - why not xml_parser_create_ns?
            $this->parser = xml_parser_create($this->xml_encoding);
            // Set the options for parsing the XML data.
            //xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
            xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
            xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, $this->xml_encoding);
            // Set the object for the parser.
            xml_set_object($this->parser, $this);
            // Set the element handlers for the parser.
            xml_set_elementHandler($this->parser, 'start_element','end_element');
            xml_set_character_dataHandler($this->parser,'character_data');

            // Parse the XML file.
            if (!xml_parse($this->parser,$xml,true)) {
                // Display an error message.
                $err = sprintf('XML error parsing SOAP payload on line %d: %s',
			    xml_get_current_line_number($this->parser),
			    xml_error_string(xml_get_error_code($this->parser)));
                $this->debug($err);
                $this->debug("XML payload:\n" . $xml);
                $this->setError($err);
            } else {
                $this->debug('parsed successfully, found root struct: ' . $this->root_struct . ' of name ' . $this->root_struct_name);
                // get final value
                $this->soapresponse = $this->message[$this->root_struct]['result'];
                // get header value: no, because this is documented as XML string
                //				if($this->root_header != '' && isset($this->message[$this->root_header]['result'])){
                //					$this->responseHeaders = $this->message[$this->root_header]['result'];
                //				}
                // resolve hrefs/ids
                if (count($this->multirefs) > 0) {
                    foreach ($this->multirefs as $id => $hrefs) {
                        $this->debug('resolving multirefs for id: ' . $id);
                        $idVal = $this->buildVal($this->ids[$id]);
                        if (is_array($idVal) && isset($idVal['!id'])) {
                            unset($idVal['!id']);
                        }
                        foreach ($hrefs as $refPos => $ref) {
                            $this->debug('resolving href at pos ' . $refPos);
                            $this->multirefs[$id][$refPos] = $idVal;
                        }
                    }
                }
            }
            xml_parser_free($this->parser);
        } else {
            $this->debug('xml was empty, didn\'t parse!');
            $this->setError('xml was empty, didn\'t parse!');
        }
    }

    /**
     * start-element handler
     *
     * @param    resource $parser XML parser object
     * @param    string $name element name
     * @param    array $attrs associative array of attributes
     */
    public function start_element($parser, $name, $attrs)
    {
        // position in a total number of elements, starting from 0
        // update class level pos
        $pos = $this->position++;
        // and set mine
        $this->message[$pos] = ['pos' => $pos, 'children' => '', 'cdata' => ''];
        // depth = how many levels removed from root?
        // set mine as current global depth and increment global depth value
        $this->message[$pos]['depth'] = $this->depth++;

        // else add self as child to whoever the current parent is
        if (0 != $pos) {
            $this->message[$this->parent]['children'] .= '|' . $pos;
        }
        // set my parent
        $this->message[$pos]['parent'] = $this->parent;
        // set self as current parent
        $this->parent = $pos;
        // set self as current value for this depth
        $this->depth_array[$this->depth] = $pos;
        // get element prefix
        if (mb_strpos($name,':')) {
            // get ns prefix
            $prefix = mb_substr($name,0,mb_strpos($name,':'));
            // get unqualified name
            $name = mb_substr(mb_strstr($name,':'),1);
        }
        // set status
        if ('Envelope' == $name) {
            $this->status = 'envelope';
        } elseif ('Header' == $name) {
            $this->root_header = $pos;
            $this->status = 'header';
        } elseif ('Body' == $name) {
            $this->status = 'body';
            $this->body_position = $pos;
        // set method
        } elseif ('body' == $this->status && $pos == ($this->body_position + 1)) {
            $this->status = 'method';
            $this->root_struct_name = $name;
            $this->root_struct = $pos;
            $this->message[$pos]['type'] = 'struct';
            $this->debug("found root struct $this->root_struct_name, pos $this->root_struct");
        }
        // set my status
        $this->message[$pos]['status'] = $this->status;
        // set name
        $this->message[$pos]['name'] = htmlspecialchars($name);
        // set attrs
        $this->message[$pos]['attrs'] = $attrs;

        // loop through atts, logging ns and type declarations
        $attstr = '';
        foreach ($attrs as $key => $value) {
            $key_prefix = $this->getPrefix($key);
            $key_localpart = $this->getLocalPart($key);
            // if ns declarations, add to class level array of valid namespaces
            if ('xmlns' == $key_prefix) {
                if (ereg('^http://www.w3.org/[0-9]{4}/XMLSchema$',$value)) {
                    $this->XMLSchemaVersion = $value;
                    $this->namespaces['xsd'] = $this->XMLSchemaVersion;
                    $this->namespaces['xsi'] = $this->XMLSchemaVersion . '-instance';
                }
                $this->namespaces[$key_localpart] = $value;
                // set method namespace
                if ($name == $this->root_struct_name) {
                    $this->methodNamespace = $value;
                }
                // if it's a type declaration, set type
            } elseif ('type' == $key_localpart) {
                $value_prefix = $this->getPrefix($value);
                $value_localpart = $this->getLocalPart($value);
                $this->message[$pos]['type'] = $value_localpart;
                $this->message[$pos]['typePrefix'] = $value_prefix;
                if (isset($this->namespaces[$value_prefix])) {
                    $this->message[$pos]['type_namespace'] = $this->namespaces[$value_prefix];
                } else {
                    if (isset($attrs['xmlns:' . $value_prefix])) {
                        $this->message[$pos]['type_namespace'] = $attrs['xmlns:' . $value_prefix];
                    }
                }
                // should do something here with the namespace of specified type?
            } elseif ('arrayType' == $key_localpart) {
                $this->message[$pos]['type'] = 'array';
                /* do arrayType ereg here
                [1]    arrayTypeValue    ::=    atype asize
                [2]    atype    ::=    QName rank*
                [3]    rank    ::=    '[' (',')* ']'
                [4]    asize    ::=    '[' length~ ']'
                [5]    length    ::=    nextDimension* Digit+
                [6]    nextDimension    ::=    Digit+ ','
                */
                $expr = '([A-Za-z0-9_]+):([A-Za-z]+[A-Za-z0-9_]+)\[([0-9]+),?([0-9]*)\]';
                if (ereg($expr,$value,$regs)) {
                    $this->message[$pos]['typePrefix'] = $regs[1];
                    $this->message[$pos]['arrayTypePrefix'] = $regs[1];
                    if (isset($this->namespaces[$regs[1]])) {
                        $this->message[$pos]['arrayTypeNamespace'] = $this->namespaces[$regs[1]];
                    } else {
                        if (isset($attrs['xmlns:' . $regs[1]])) {
                            $this->message[$pos]['arrayTypeNamespace'] = $attrs['xmlns:' . $regs[1]];
                        }
                    }
                    $this->message[$pos]['arrayType'] = $regs[2];
                    $this->message[$pos]['arraySize'] = $regs[3];
                    $this->message[$pos]['arrayCols'] = $regs[4];
                }
                // specifies nil value (or not)
            } elseif ('nil' == $key_localpart) {
                $this->message[$pos]['nil'] = ('true' == $value || '1' == $value);
            // some other attribute
            } elseif ('href' != $key && 'xmlns' != $key && 'encodingStyle' != $key_localpart && 'root' != $key_localpart) {
                $this->message[$pos]['xattrs']['!' . $key] = $value;
            }

            if ('xmlns' == $key) {
                $this->default_namespace = $value;
            }
            // log id
            if ('id' == $key) {
                $this->ids[$value] = $pos;
            }
            // root
            if ('root' == $key_localpart && 1 == $value) {
                $this->status = 'method';
                $this->root_struct_name = $name;
                $this->root_struct = $pos;
                $this->debug("found root struct $this->root_struct_name, pos $pos");
            }
            // for doclit
            $attstr .= " $key=\"$value\"";
        }
        // get namespace - must be done after namespace atts are processed
        if (isset($prefix)) {
            $this->message[$pos]['namespace'] = $this->namespaces[$prefix];
            $this->default_namespace = $this->namespaces[$prefix];
        } else {
            $this->message[$pos]['namespace'] = $this->default_namespace;
        }
        if ('header' == $this->status) {
            if ($this->root_header != $pos) {
                $this->responseHeaders .= '<' . (isset($prefix) ? $prefix . ':' : '') . "$name$attstr>";
            }
        } elseif ('' != $this->root_struct_name) {
            $this->document .= '<' . (isset($prefix) ? $prefix . ':' : '') . "$name$attstr>";
        }
    }

    /**
     * end-element handler
     *
     * @param    resource $parser XML parser object
     * @param    string $name element name
     */
    public function end_element($parser, $name)
    {
        // position of current element is equal to the last value left in depth_array for my depth
        $pos = $this->depth_array[$this->depth--];

        // get element prefix
        if (mb_strpos($name,':')) {
            // get ns prefix
            $prefix = mb_substr($name,0,mb_strpos($name,':'));
            // get unqualified name
            $name = mb_substr(mb_strstr($name,':'),1);
        }

        // build to native type
        if (isset($this->body_position) && $pos > $this->body_position) {
            // deal w/ multirefs
            if (isset($this->message[$pos]['attrs']['href'])) {
                // get id
                $id = mb_substr($this->message[$pos]['attrs']['href'],1);
                // add placeholder to href array
                $this->multirefs[$id][$pos] = 'placeholder';
                // add set a reference to it as the result value
                $this->message[$pos]['result'] = &$this->multirefs[$id][$pos];
            // build complexType values
            } elseif ('' != $this->message[$pos]['children']) {
                // if result has already been generated (struct/array)
                if (!isset($this->message[$pos]['result'])) {
                    $this->message[$pos]['result'] = $this->buildVal($pos);
                }
                // build complexType values of attributes and possibly simpleContent
            } elseif (isset($this->message[$pos]['xattrs'])) {
                if (isset($this->message[$pos]['nil']) && $this->message[$pos]['nil']) {
                    $this->message[$pos]['xattrs']['!'] = null;
                } elseif (isset($this->message[$pos]['cdata']) && '' != trim($this->message[$pos]['cdata'])) {
                    if (isset($this->message[$pos]['type'])) {
                        $this->message[$pos]['xattrs']['!'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$pos]['type'], isset($this->message[$pos]['type_namespace']) ? $this->message[$pos]['type_namespace'] : '');
                    } else {
                        $parent = $this->message[$pos]['parent'];
                        if (isset($this->message[$parent]['type']) && ('array' == $this->message[$parent]['type']) && isset($this->message[$parent]['arrayType'])) {
                            $this->message[$pos]['xattrs']['!'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$parent]['arrayType'], isset($this->message[$parent]['arrayTypeNamespace']) ? $this->message[$parent]['arrayTypeNamespace'] : '');
                        } else {
                            $this->message[$pos]['xattrs']['!'] = $this->message[$pos]['cdata'];
                        }
                    }
                }
                $this->message[$pos]['result'] = $this->message[$pos]['xattrs'];
            // set value of simpleType (or nil complexType)
            } else {
                //$this->debug('adding data for scalar value '.$this->message[$pos]['name'].' of value '.$this->message[$pos]['cdata']);
                if (isset($this->message[$pos]['nil']) && $this->message[$pos]['nil']) {
                    $this->message[$pos]['xattrs']['!'] = null;
                } elseif (isset($this->message[$pos]['type'])) {
                    $this->message[$pos]['result'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$pos]['type'], isset($this->message[$pos]['type_namespace']) ? $this->message[$pos]['type_namespace'] : '');
                } else {
                    $parent = $this->message[$pos]['parent'];
                    if (isset($this->message[$parent]['type']) && ('array' == $this->message[$parent]['type']) && isset($this->message[$parent]['arrayType'])) {
                        $this->message[$pos]['result'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$parent]['arrayType'], isset($this->message[$parent]['arrayTypeNamespace']) ? $this->message[$parent]['arrayTypeNamespace'] : '');
                    } else {
                        $this->message[$pos]['result'] = $this->message[$pos]['cdata'];
                    }
                }

                /* add value to parent's result, if parent is struct/array
                $parent = $this->message[$pos]['parent'];
                if($this->message[$parent]['type'] != 'map'){
                	if(strtolower($this->message[$parent]['type']) == 'array'){
                		$this->message[$parent]['result'][] = $this->message[$pos]['result'];
                	} else {
                		$this->message[$parent]['result'][$this->message[$pos]['name']] = $this->message[$pos]['result'];
                	}
                }
                */
            }
        }

        // for doclit
        if ('header' == $this->status) {
            if ($this->root_header != $pos) {
                $this->responseHeaders .= '</' . (isset($prefix) ? $prefix . ':' : '') . "$name>";
            }
        } elseif ($pos >= $this->root_struct) {
            $this->document .= '</' . (isset($prefix) ? $prefix . ':' : '') . "$name>";
        }
        // switch status
        if ($pos == $this->root_struct) {
            $this->status = 'body';
            $this->root_struct_namespace = $this->message[$pos]['namespace'];
        } elseif ('Body' == $name) {
            $this->status = 'envelope';
        } elseif ('Header' == $name) {
            $this->status = 'envelope';
        } elseif ('Envelope' == $name) {
        }
        // set parent back to my parent
        $this->parent = $this->message[$pos]['parent'];
    }

    /**
     * element content handler
     *
     * @param    resource $parser XML parser object
     * @param    string $data element content
     */
    public function character_data($parser, $data)
    {
        $pos = $this->depth_array[$this->depth];
        if ('UTF-8' == $this->xml_encoding) {
            // TODO: add an option to disable this for folks who want
            // raw UTF-8 that, e.g., might not map to iso-8859-1
            // TODO: this can also be handled with xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
            if ($this->decode_utf8) {
                $data = utf8_decode($data);
            }
        }
        $this->message[$pos]['cdata'] .= $data;
        // for doclit
        if ('header' == $this->status) {
            $this->responseHeaders .= $data;
        } else {
            $this->document .= $data;
        }
    }

    /**
     * get the parsed message
     *
     * @return	mixed
     */
    public function get_response()
    {
        return $this->soapresponse;
    }

    /**
     * get the parsed headers
     *
     * @return	string XML or empty if no headers
     */
    public function getHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * decodes simple types into PHP variables
     *
     * @param    string $value value to decode
     * @param    string $type XML type to decode
     * @param    string $typens XML type namespace to decode
     * @return	mixed PHP value
     */
    public function decodeSimple($value, $type, $typens)
    {
        // TODO: use the namespace!
        if ((!isset($type)) || 'string' == $type || 'long' == $type || 'unsignedLong' == $type) {
            return (string) $value;
        }
        if ('int' == $type || 'integer' == $type || 'short' == $type || 'byte' == $type) {
            return (int) $value;
        }
        if ('float' == $type || 'double' == $type || 'decimal' == $type) {
            return (float) $value;
        }
        if ('boolean' == $type) {
            if ('false' == mb_strtolower($value) || 'f' == mb_strtolower($value)) {
                return false;
            }

            return (bool) $value;
        }
        if ('base64' == $type || 'base64Binary' == $type) {
            $this->debug('Decode base64 value');

            return base64_decode($value, true);
        }
        // obscure numeric types
        if ('nonPositiveInteger' == $type || 'negativeInteger' == $type
			|| 'nonNegativeInteger' == $type || 'positiveInteger' == $type
			|| 'unsignedInt' == $type
			|| 'unsignedShort' == $type || 'unsignedByte' == $type) {
            return (int) $value;
        }
        // bogus: parser treats array with no elements as a simple type
        if ('array' == $type) {
            return [];
        }
        // everything else
        return (string) $value;
    }

    /**
     * builds response structures for compound values (arrays/structs)
     * and scalars
     *
     * @param    int $pos position in node tree
     * @return	mixed	PHP value
     */
    public function buildVal($pos)
    {
        if (!isset($this->message[$pos]['type'])) {
            $this->message[$pos]['type'] = '';
        }
        $this->debug('in buildVal() for ' . $this->message[$pos]['name'] . "(pos $pos) of type " . $this->message[$pos]['type']);
        // if there are children...
        if ('' != $this->message[$pos]['children']) {
            $this->debug('in buildVal, there are children');
            $children = explode('|',$this->message[$pos]['children']);
            array_shift($children); // knock off empty
            // md array
            if (isset($this->message[$pos]['arrayCols']) && '' != $this->message[$pos]['arrayCols']) {
                $r = 0; // rowcount
            	$c = 0; // colcount
            	foreach ($children as $child_pos) {
            	    $this->debug("in buildVal, got an MD array element: $r, $c");
            	    $params[$r][] = $this->message[$child_pos]['result'];
            	    $c++;
            	    if ($c == $this->message[$pos]['arrayCols']) {
            	        $c = 0;
            	        $r++;
            	    }
            	}
                // array
            } elseif ('array' == $this->message[$pos]['type'] || 'Array' == $this->message[$pos]['type']) {
                $this->debug('in buildVal, adding array ' . $this->message[$pos]['name']);
                foreach ($children as $child_pos) {
                    $params[] = &$this->message[$child_pos]['result'];
                }
                // apache Map type: java hashtable
            } elseif ('Map' == $this->message[$pos]['type'] && 'http://xml.apache.org/xml-soap' == $this->message[$pos]['type_namespace']) {
                $this->debug('in buildVal, Java Map ' . $this->message[$pos]['name']);
                foreach ($children as $child_pos) {
                    $kv = explode('|',$this->message[$child_pos]['children']);
                    $params[$this->message[$kv[1]]['result']] = &$this->message[$kv[2]]['result'];
                }
                // generic compound type
            //} elseif($this->message[$pos]['type'] == 'SOAPStruct' || $this->message[$pos]['type'] == 'struct') {
            } else {
                // Apache Vector type: treat as an array
                $this->debug('in buildVal, adding Java Vector ' . $this->message[$pos]['name']);
                if ('Vector' == $this->message[$pos]['type'] && 'http://xml.apache.org/xml-soap' == $this->message[$pos]['type_namespace']) {
                    $notstruct = 1;
                } else {
                    $notstruct = 0;
                }

                foreach ($children as $child_pos) {
                    if ($notstruct) {
                        $params[] = &$this->message[$child_pos]['result'];
                    } else {
                        if (isset($params[$this->message[$child_pos]['name']])) {
                            // de-serialize repeated element name into an array
                            if ((!is_array($params[$this->message[$child_pos]['name']])) || (!isset($params[$this->message[$child_pos]['name']][0]))) {
                                $params[$this->message[$child_pos]['name']] = [$params[$this->message[$child_pos]['name']]];
                            }
                            $params[$this->message[$child_pos]['name']][] = &$this->message[$child_pos]['result'];
                        } else {
                            $params[$this->message[$child_pos]['name']] = &$this->message[$child_pos]['result'];
                        }
                    }
                }
            }
            if (isset($this->message[$pos]['xattrs'])) {
                $this->debug('in buildVal, handling attributes');
                foreach ($this->message[$pos]['xattrs'] as $n => $v) {
                    $params[$n] = $v;
                }
            }
            // handle simpleContent
            if (isset($this->message[$pos]['cdata']) && '' != trim($this->message[$pos]['cdata'])) {
                $this->debug('in buildVal, handling simpleContent');
                if (isset($this->message[$pos]['type'])) {
                    $params['!'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$pos]['type'], isset($this->message[$pos]['type_namespace']) ? $this->message[$pos]['type_namespace'] : '');
                } else {
                    $parent = $this->message[$pos]['parent'];
                    if (isset($this->message[$parent]['type']) && ('array' == $this->message[$parent]['type']) && isset($this->message[$parent]['arrayType'])) {
                        $params['!'] = $this->decodeSimple($this->message[$pos]['cdata'], $this->message[$parent]['arrayType'], isset($this->message[$parent]['arrayTypeNamespace']) ? $this->message[$parent]['arrayTypeNamespace'] : '');
                    } else {
                        $params['!'] = $this->message[$pos]['cdata'];
                    }
                }
            }

            return is_array($params) ? $params : [];
        }  
        $this->debug('in buildVal, no children, building scalar');
        $cdata = isset($this->message[$pos]['cdata']) ? $this->message[$pos]['cdata'] : '';
        if (isset($this->message[$pos]['type'])) {
            return $this->decodeSimple($cdata, $this->message[$pos]['type'], isset($this->message[$pos]['type_namespace']) ? $this->message[$pos]['type_namespace'] : '');
        }
        $parent = $this->message[$pos]['parent'];
        if (isset($this->message[$parent]['type']) && ('array' == $this->message[$parent]['type']) && isset($this->message[$parent]['arrayType'])) {
            return $this->decodeSimple($cdata, $this->message[$parent]['arrayType'], isset($this->message[$parent]['arrayTypeNamespace']) ? $this->message[$parent]['arrayTypeNamespace'] : '');
        }

        return $this->message[$pos]['cdata'];
    }
}

?>