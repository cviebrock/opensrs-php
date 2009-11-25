<?php

require_once 'PEAR.php';

define ('OPS_OK',					1 );
define ('OPS_ERROR',				-1 );
define ('OPS_ERROR_TIMEOUT',		-2 );
define ('OPS_ERROR_EOF',			-3 );
define ('OPS_ERROR_NO_DATA',		-4 );
define ('OPS_ERROR_SHORT_READ',		-5 );
define ('OPS_ERROR_UNEXPECTED',		-6 );
define ('OPS_ERROR_CANT_DECODE',	-7 );
define ('OPS_ERROR_CANT_ENCODE',	-8 );
define ('OPS_ERROR_NO_SOCKET',		-9 );
define ('OPS_ERROR_CANT_WRITE',		-10 );

class OPS extends PEAR {

	var $_OPS_VERSION		= '0.9';

	var $_OUTPUT_COMPRESS	= false;
	var $_OUTPUT_INDENT		= ' ';
	var $_OUTPUT_LF			= "\n";

	var $_MSGTYPE_STD		= 'standard';
	var $_SESSID;
	var $_MSGCNT;

	var $CRLF			= "\r\n";

	var $_log			= array();

	var $_data;
	var $_pointers;
	var $_last_was_data_block;



	/**
	 * Class constructor
	 *
	 * Initialize variables, logs, etc.
	 *
	 * @param	array	allows for setting various options (right now, just whether
	 *					to use compression or not on the generated XML)
	 */

	function OPS($args=false)
	{

		$this->PEAR();

		if (is_array($args)) {
			if ($args['compress']==true) {
				$this->_OUTPUT_COMPRESS	= true;
				$this->_OUTPUT_INDENT	= '';
				$this->_OUTPUT_LF		= '';
			}
		}

		$this->_SESSID = getmypid();
		$this->_MSGCNT = 0;

		$this->_log('raw','i','OPS Raw Log:');
		$this->_log('raw','i','Initialized '.date('r') );

		$this->_log('xml','i','OPS XML Log:');
		$this->_log('xml','i','Initialized '.date('r') );

	}


	function raiseError($code, $info=NULL)
	{
		return PEAR::raiseError( $this->errorMessage($code), $code, NULL, NULL, $info );
	}


	function errorMessage($value)
	{
		static $errorMessages;
		if (!isset($errorMessages)) {
			$errorMessages = array(
				OPS_OK					=> 'OK',
				OPS_ERROR				=> 'unknown error',
				OPS_ERROR_TIMEOUT		=> 'timeout reading',
				OPS_ERROR_EOF			=> 'EOF reached',
				OPS_ERROR_NO_DATA		=> 'no data read',
				OPS_ERROR_SHORT_READ	=> 'short read',
				OPS_ERROR_UNEXPECTED	=> 'unexpected data read',
				OPS_ERROR_CANT_DECODE	=> 'can\'t decode data',
				OPS_ERROR_CANT_ENCODE	=> 'can\'t encode data',
				OPS_ERROR_NO_SOCKET		=> 'socket not active',
				OPS_ERROR_CANT_WRITE	=> 'can\'t write to socket',
			);
		}

		if (PEAR::isError($value)) {
			$value = $value->getCode();
		}

		return isset($errorMessages[$value]) ? $errorMessages[$value] : $errorMessages[OPS_ERROR];

	}


	/**
	 * Writes a message to a socket (buffered IO)
	 *
	 * @param	int 	socket handle
	 *
	 * @param	string 	message to write
	 *
	 */

	function writeData(&$fh,$msg)
	{
		$len = strlen($msg);

		if (!fputs($fh, 'Content-Length: ' . $len . $this->CRLF . $this->CRLF) ) {
			return $this->raiseError( OPS_ERROR_CANT_WRITE );
		}
		if (!fputs($fh, $msg, $len ) ) {
			return $this->raiseError( OPS_ERROR_CANT_WRITE );
		}

		$this->_log('raw', 'w', $msg, $len);

		return OPS_OK;

	}


	/**
	 * Encodes and writes a message to a socket
	 *
	 * @param	int 	socket handle
	 *
	 * @param	string 	message to encode and write
	 *
	 * @return	mixed	OK, or an error
	 *
	 */

	function writeMessage(&$fh, $hr )
	{
		$msg = $this->encode( $hr );
		if (PEAR::isError($msg)) {
			return $msg;
		}

		return $this->writeData($fh, $msg );
		
	}


	/**
	 * Reads data from a socket
	 *
	 * @param	int 	socket handle
	 *
	 * @param	int 	timeout for read
	 *
	 * @return	mixed	buffer with data, or an error
	 *
	 */

	function readData(&$fh, $timeout=5)
	{
		$len = 0;

		/* PHP doesn't have timeout for fread ... we just set the timeout for the socket */

		socket_set_timeout($fh, $timeout);

		$line = fgets($fh, 4000);
		$test = $this->socketStatus($fh);

		if (PEAR::isError($test)) {
			return $test;
		}

		if (!$len && preg_match('/^\s*Content-Length:\s+(\d+)\s*\r\n/i', $line, $matches ) ) {
			$len = (int)$matches[1];
		} else {
			$this->_log('raw', 'e', 'UNEXPECTED READ: No Content-Length' );
			$this->_log('raw', 'r', $line);
			return $this->raiseError( OPS_ERROR_UNEXPECTED, 'No content-length' );
		}

		/* read the empty line */

		$line = fread($fh, 2);
		$test = $this->socketStatus($fh);
		if (PEAR::isError($test)) {
			return $test;
		}

		if ($line!=$this->CRLF) {
			$this->_log('raw', 'e', 'UNEXPECTED READ: No CRLF');
			$this->_log('raw', 'r', $line);
			return $this->raiseError( OPS_ERROR_UNEXPECTED, 'No CRLF' );
		}

		$line = '';
		while (strlen($line) < $len) {
			$line .= fread($fh, $len);
			$test = $this->socketStatus($fh);
			if (PEAR::isError($test)) {
				return $test;
			}
		}

		if ($line=='') {
			$this->_log('raw', 'e', 'NO DATA READ' );
			return $this->raiseError( OPS_ERROR_NO_DATA );
		}

		if (strlen($line) != $len ) {
			$this->_log('raw', 'e', 'NEXT LINE SHORT READ (expecting '.$len.', read ' . strlen($line) . ')' );
			$this->_log('raw', 'r', $line);
			return $this->raiseError( OPS_ERROR_SHORT_READ );
		}

		$this->_log('raw', 'r', $line);
		return $line;

	}


	/**
	 * Reads and decodes data from a socket
	 *
	 * @param	int 	socket handle
	 *
	 * @param	int 	timeout for read
	 *
	 * @return	mixed	associative array of data, or an error
	 *
	 */
	function readMessage(&$fh, $timeout=5)
	{
		$buf = $this->readData($fh, $timeout);
		if (PEAR::isError($buf)) {
			return $buf;
		}
		return $this->decode($buf);
	}



	/**
	 * Checks a socket for timeout or EOF
	 *
	 * @param	int 		socket handle
	 *
	 * @return	mixed 		null if the socket is ok, error otherwise
	 *
	 */

	function socketStatus(&$fh)
	{
		if (!is_resource($fh)) {
			return $this->raiseError( OPS_ERROR_NO_SOCKET );
		}

		$temp = socket_get_status($fh);

		if ($temp['timed_out']) {
			$this->_log('raw', 'e', 'SOCKET TIMED OUT');
			return $this->raiseError( OPS_ERROR_TIMEOUT );
		}

		if ($temp['eof']) {
			$this->_log('raw', 'e', 'SOCKET EOF');
			return $this->raiseError( OPS_ERROR_EOF );
		}

		//return OPS_OK;

	}




#
#	DECODING METHODS
#	Converts XML OPS messages into PHP data
#


	/**
	 * Accepts an OPS protocol message or an file handle
	 * and decodes the data into a PHP array
	 *
	 * @param	string 		OPS message
	 *
	 * @return	mixed		PHP array, or error
	 *
	 */

	function decode($in)
	{

		$ops_msg = '';

		/* determine if we were passed a string or file handle */

		if (is_resource($in)) {
			/* read the file into a string, then process as usual */
			while (!feof($in)) {
				$ops_msg .= fgets($in, 400);
			}
		} else {
			$ops_msg = $in;
		}


		/* log it first */

		$this->_log('xml', 'r', $ops_msg);


		/* decode and return */

		return $this->XML2PHP($ops_msg);


	}


	/**
	 * XML Parser that converts an OPS protocol message into a PHP array
	 *
	 * @param	string 		OPS message
	 *
	 * @return	mixed		PHP array, or error
	 *
	 */

	function XML2PHP($msg) {

		$this->_data = NULL;

		$xp = xml_parser_create();
		xml_parser_set_option($xp, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($xp, XML_OPTION_SKIP_WHITE, true);
		xml_parser_set_option($xp, XML_OPTION_TARGET_ENCODING, 'ISO-8859-1');

		if (!xml_parse_into_struct($xp,$msg,$vals,$index)) {
			$error = sprintf('XML error: %s at line %d',
				xml_error_string(xml_get_error_code($xp)),
				xml_get_current_line_number($xp)
			);
			xml_parser_free($xp);
			return $this->raiseError( OPS_ERROR_CANT_DECODE, $error );
		}

		xml_parser_free($xp);

		$temp = $depth = array();

		foreach($vals as $value) {

			switch ($value['tag']) {
			  case 'OPS_envelope':
			  case 'header':
			  case 'body':
			  case 'data_block':
				break;

			  case 'version':
			  case 'msg_id':
			  case 'msg_type':
				$key = '_OPS_' . $value['tag'];
				$temp[$key] = $value['value'];
				break;

			  case 'item':
				//$key = $value['attributes']['key'];

				switch ($value['type']) {
				  case 'open':
				  $key = $value['attributes']['key'];
					array_push($depth, $key);
					break;

				  case 'complete':
				  $key = $value['attributes']['key'];
					array_push($depth, $key);
					$p = join('::',$depth);
					//$temp[$p] = $value['value'];
					$temp[$p] = (isset($value['value']) ? $value['value'] : NULL);
					array_pop($depth);
					break;

				  case 'close':
					array_pop($depth);
					break;

				}

				break;

			  case 'dt_assoc':
			  case 'dt_array':
				break;

			}
		}

		foreach ($temp as $key=>$value) {

			$levels = explode('::',$key);
			$num_levels = count($levels);

			if ($num_levels==1) {
				$this->_data[$levels[0]] = $value;
			} else {
				$pointer = &$this->_data;
				for ($i=0; $i<$num_levels; $i++) {
					if ( !isset( $pointer[$levels[$i]] ) ) {
						$pointer[$levels[$i]] = array();
					}
					$pointer = &$pointer[$levels[$i]];
				}
				$pointer = $value;
			}

		}

		return ($this->_data);

	}



#
#	ENCODING METHODS
#	Converts PHP data into XML OPS messages
#


	/**
	 * Converts a PHP array into an OPS message
	 *
	 * @param	array		PHP array
	 *
	 * @return 	string		OPS XML message
	 *
	 */

	function encode($array)
	{

		$this->_MSGCNT++;
		$msg_id = $this->_SESSID + $this->_MSGCNT;			/* addition removes the leading zero */
		$msg_type = $this->_MSGTYPE_STD;


		if (!empty($array['protocol'])) {
			$array['protocol'] = strtoupper($array['protocol']);
		}
		if (!empty($array['action'])) {
			$array['action'] = strtoupper($array['action']);
		}
		if (!empty($array['object'])) {
			$array['object'] = strtoupper($array['object']);
		}

		$xml_data_block = $this->PHP2XML($array);

		$ops_msg = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . $this->_OUTPUT_LF .
			'<!DOCTYPE OPS_envelope SYSTEM "ops.dtd">' . $this->_OUTPUT_LF .
			'<OPS_envelope>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . '<header>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . $this->_OUTPUT_INDENT . '<version>' . $this->_OPS_VERSION . '</version>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . $this->_OUTPUT_INDENT . '<msg_id>' . $msg_id . '</msg_id>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . $this->_OUTPUT_INDENT . '<msg_type>' . $msg_type . '</msg_type>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . '</header>' . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . '<body>' . $this->_OUTPUT_LF .
			$xml_data_block . $this->_OUTPUT_LF .
			$this->_OUTPUT_INDENT . '</body>' . $this->_OUTPUT_LF .
			'</OPS_envelope>'; // <?php // highlighting fix

		# log it

		$this->_log('xml', 'w', $ops_msg);

		return $ops_msg;

	}



	/**
	 * Converts a PHP array into an OPS data_block tag
	 *
	 * @param	array		PHP array
	 *
	 * @return 	string		OPS data_block tag
	 *
	 */

	function PHP2XML($data)
	{
		return str_repeat($this->_OUTPUT_INDENT,2) . '<data_block>' .
			$this->_convertData($data, 3) .
			$this->_OUTPUT_LF . str_repeat($this->_OUTPUT_INDENT,2) . '</data_block>';
	}


	/**
	 * Recursivly converts PHP data into XML
	 *
	 * @param	mixed		PHP array or data
	 *
	 * @param	int			ident level
	 *
	 * @return 	string		XML string
	 *
	 */

	function _convertData(&$array, $indent=0) 
	{
		$string = '';
		$IND = str_repeat($this->_OUTPUT_INDENT,$indent);

		if (is_array($array)) {

			if ($this->_is_assoc($array)) {		# HASH REFERENCE
				$string .= $this->_OUTPUT_LF . $IND . '<dt_assoc>';
				$end = '</dt_assoc>';
			} else {				# ARRAY REFERENCE
				$string .= $this->_OUTPUT_LF . $IND . '<dt_array>';
				$end = '</dt_array>';
			}

			foreach ($array as $k=>$v) {

				$indent++;

				/* don't encode some types of stuff */
				if ((gettype($v)=='resource') || (gettype($v)=='user function') || (gettype($v)=='unknown type')) {
					continue;
				}

				/* hack for an empty associative array */
				if (empty($k) && $v===NULL) {
					continue;
				}

				$string .= $this->_OUTPUT_LF . $IND . '<item key="' . $k . '"';

				if (gettype($v)=='object' && get_class($v)) {
					$string .= ' class="' . get_class($v) . '"';
				}

				$string .= '>';

				if (is_array($v) || is_object($v)) {
					$string .= $this->_convertData($v, $indent+1);
					$string .= $this->_OUTPUT_LF . $IND . '</item>';
				} else {
					$string .= $this->_quoteXMLChars($v) . '</item>';
				}

				$indent--;
			}

			$string .= $this->_OUTPUT_LF . $IND . $end;

		} else {					# SCALAR

			$string .= $this->_OUTPUT_LF . $IND . '<dt_scalar>' .
				$this->_quoteXMLChars($array) . '</dt_scalar>';
		}

		return $string;

	}



	/**
	 * Quotes special XML characters
	 *
	 * @param	string		string to quote
	 *
	 * @return 	string		quoted string
	 *
	 */

	function _quoteXMLChars($string) 
	{
		$search  = array ('&', '<', '>', "'", '"');
		$replace = array ('&amp;', '&lt;', '&gt;', '&apos;', '&quot;');
		$string = str_replace($search, $replace, $string);
		$string = utf8_encode($string);
		return $string;
	}




	/**
	 * Determines if an array is associative or not, since PHP
	 * doesn't really distinguish between the two, but Perl/OPS does
	 *
	 * @param	array		array to check
	 *
	 * @return 	boolean		true if the array is associative
	 *
	 */

	function _is_assoc(&$array)
	{
		if (is_array($array)) {
			foreach ($array as $k=>$v) {
				if (!is_int($k)) {
					return true;
				}
			}
		}
		return false;
	}




	/**
	 * Internal loggging method
	 *
	 * @param	string 		which log to log to
	 *
	 * @param	string		type of log message ('r'ead, 'w'rite, 'i'nfo or 'e'rror)
	 *
	 * @param	int			message
	 *
	 */

	function _log($log, $type, $msg)
	{
		$types = array(
			'r'	=> 'read',
			'w'	=> 'write',
			'e'	=> 'error',
			'i' => 'info'
		);

		if ($log=='xml') {
			$this->log[$log][] = sprintf("[% 6s:%06d] %s\n",
				strtoupper($types[$type]),
				($type=='e' || $type=='i') ? 0 : strlen($msg),
				$msg
			);
		} else {
			$this->log[$log][] = sprintf("[% 6s:%06d] %s\n",
				strtoupper($types[$type]),
				($type=='e' || $type=='i') ? 0 : strlen($msg),
				($type=='e' || $type=='i') ? $msg : bin2hex($msg)
			);
		}
	}


	/**
	 * Show internal log
	 *
	 * @param	string 		which log to log show, 'raw' or 'xml'
	 *
	 * @param	string		format to display: 'html' (default) or 'raw'
	 *
	 */

	function showLog($log, $format='html')
	{
		echo '<PRE>';
		foreach ($this->log[$log] as $line) {
			switch ($format) {
			  case 'raw':
				echo $line . "\n";
				break;
			  case 'html':
			  default:
				echo htmlEntities($line) . "\n";
				break;
			}
		}
		echo '</PRE>';
	}



}


