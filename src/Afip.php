<?php
/**
 * Software Development Kit for AFIP web services
 * 
 * This release of Afip SDK is intended to facilitate 
 * the integration to other different web services that 
 * Electronic Billing   
 *
 * @link http://www.afip.gob.ar/ws/ AFIP Web Services documentation
 *
 * @author 	Afip SDK afipsdk@gmail.com
 * @package Afip
 * @version 0.5
 **/

#[\AllowDynamicProperties]
class Afip {
	/**
	 * Options received in the constructor
	 *
	 * @var array
	 **/
	var $options;

	/**
	 * File name for the WSDL corresponding to WSAA
	 *
	 * @var string
	 **/
	var $WSAA_WSDL;

	/**
	 * The url to get WSAA token
	 *
	 * @var string
	 **/
	var $WSAA_URL;

	/**
	 * File name for the X.509 certificate in PEM format
	 *
	 * @var string
	 **/
	var $CERT;

	/**
	 * File name for the private key correspoding to CERT (PEM)
	 *
	 * @var string
	 **/
	var $PRIVATEKEY;

	/**
	 * The passphrase (if any) to sign
	 *
	 * @var string
	 **/
	var $PASSPHRASE;

	/**
	 * Afip resources folder
	 *
	 * @var string
	 **/
	var $RES_FOLDER;

	/**
	 * Afip ta folder
	 *
	 * @var string
	 **/
	var $TA_FOLDER;

	/**
	 * The CUIT to use
	 *
	 * @var int
	 **/
	var $CUIT;

	/**
	 * Implemented Web Services
	 *
	 * @var array[string]
	 **/
	var $implemented_ws = array(
		'ElectronicBilling',
		'RegisterScopeFour',
		'RegisterScopeFive',
		'RegisterScopeTen',
		'RegisterScopeThirteen'
	);

	function __construct($options)
	{
		ini_set("soap.wsdl_cache_enabled", "0");


		if (!isset($options['CUIT'])) {
			throw new Exception("CUIT field is required in options array");
		} else {
			$this->CUIT = $options['CUIT'];
		}

		if (!isset($options['production'])) {
			$options['production'] = FALSE;
		}

		if (!isset($options['passphrase'])) {
			$options['passphrase'] = 'xxxxx';
		}

		if (!isset($options['cert'])) {
			$options['cert'] = 'cert';
		}

		if (!isset($options['key'])) {
			$options['key'] = 'key';
		}

		if (!isset($options['res_folder'])) {
			$this->RES_FOLDER = __DIR__.'/Afip_res/';
		} else {
			$this->RES_FOLDER = $options['res_folder'];
		}

		if (!isset($options['ta_folder'])) {
			$this->TA_FOLDER = __DIR__.'/Afip_res/';
		} else {
			$this->TA_FOLDER = $options['ta_folder'];
		}

		$this->PASSPHRASE = $options['passphrase'];

		$this->options = $options;

		$this->CERT 		= $this->RES_FOLDER.$options['cert'];
		$this->PRIVATEKEY 	= $this->RES_FOLDER.$options['key'];

		$this->WSAA_WSDL 	= __DIR__.'/Afip_res/'.'wsaa.wsdl';
		if ($options['production'] === TRUE) {
			$this->WSAA_URL = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
		} else {
			$this->WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
		}

		if (!file_exists($this->CERT)) 
			throw new Exception("Failed to open ".$this->CERT."\n", 1);
		if (!file_exists($this->PRIVATEKEY)) 
			throw new Exception("Failed to open ".$this->PRIVATEKEY."\n", 2);
		if (!file_exists($this->WSAA_WSDL)) 
			throw new Exception("Failed to open ".$this->WSAA_WSDL."\n", 3);
	}

	/**
	 * Gets the SOAP timeout (seconds) configured with the 'soap_timeout'
	 * option
	 *
	 * @since 7.1
	 *
	 * @return int|null Seconds, or NULL if the option is not set
	**/
	public function GetSoapTimeout()
	{
		if (isset($this->options['soap_timeout']) && $this->options['soap_timeout'] > 0)
			return (int) ceil($this->options['soap_timeout']);

		return NULL;
	}

	/**
	 * Adds the 'soap_timeout' option (if any) to SoapClient options
	 *
	 * @since 7.1
	 *
	 * @param array $soap_options 		SoapClient options
	 * @param array $context_options 	stream_context options, by wrapper
	 *
	 * @return array SoapClient options
	**/
	public function ApplySoapTimeout($soap_options, $context_options = array())
	{
		$timeout = $this->GetSoapTimeout();

		if ($timeout !== NULL) {
			$soap_options['connection_timeout'] = $timeout;
			$context_options['http'] = array('timeout' => $timeout);
		}

		if (!empty($context_options))
			$soap_options['stream_context'] = stream_context_create($context_options);

		return $soap_options;
	}

	/**
	 * Sets default_socket_timeout (the one ext/soap uses to read the
	 * response) to 'soap_timeout' and returns the previous value
	 *
	 * @since 7.1
	 *
	 * @return string|null Previous value to restore, or NULL if there is
	 * 	nothing to restore
	**/
	public function SetSocketTimeout()
	{
		$timeout = $this->GetSoapTimeout();

		if ($timeout === NULL)
			return NULL;

		$previous = ini_set('default_socket_timeout', (string) $timeout);

		return $previous === FALSE ? NULL : $previous;
	}

	/**
	 * Restores default_socket_timeout
	 *
	 * @since 7.1
	 *
	 * @param string|null $previous Value returned by Afip::SetSocketTimeout
	 *
	 * @return void
	**/
	public function RestoreSocketTimeout($previous)
	{
		if ($previous !== NULL)
			ini_set('default_socket_timeout', $previous);
	}

	/**
	 * Gets token authorization for an AFIP Web Service
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return TokenAutorization Token Autorization for AFIP Web Service 
	**/
	public function GetServiceTA($service, $continue = TRUE)
	{
		$ta = $this->ReadServiceTA($service);

		if ($ta !== NULL)
			return $ta;
		else if ($continue === FALSE)
			throw new Exception("Error Getting TA", 5);

		if ($this->CreateServiceTA($service))
			return $this->GetServiceTA($service, FALSE);
	}

	/**
	 * Gets the key that identifies the TA of a service. It is the name of
	 * the file (without extension) in ta_folder, and the key that is passed
	 * to the 'ta_get' and 'ta_put' callbacks
	 *
	 * @since 7.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @return string
	**/
	private function GetServiceTAKey($service)
	{
		return 'TA-'.$this->options['CUIT'].'-'.$service.($this->options['production'] === TRUE ? '-production' : '');
	}

	/**
	 * Reads the stored TA of a service
	 *
	 * The TA is read from the 'ta_get' callback if it is set in options
	 * (receives the TA key, returns the TA xml or NULL/FALSE if it does not
	 * exist), if not, from a xml file in ta_folder
	 *
	 * @since 7.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @return TokenAutorization|null NULL if there is not a TA or if it is
	 * 	expired (or expires in less than 10 minutes)
	**/
	private function ReadServiceTA($service)
	{
		$key = $this->GetServiceTAKey($service);

		if (isset($this->options['ta_get']) && is_callable($this->options['ta_get'])) {
			$xml = call_user_func($this->options['ta_get'], $key);
		} else {
			$file = $this->TA_FOLDER.$key.'.xml';
			$xml = file_exists($file) ? @file_get_contents($file) : FALSE;
		}

		if (!is_string($xml) || $xml === '')
			return NULL;

		try {
			$TA = new SimpleXMLElement($xml);

			$actual_time 		= new DateTime(date('c',date('U')+600));
			$expiration_time 	= new DateTime((string) $TA->header->expirationTime);
		} catch (Exception $e) {
			return NULL;
		}

		if ($actual_time < $expiration_time)
			return new TokenAutorization($TA->credentials->token, $TA->credentials->sign);

		return NULL;
	}

	/**
	 * Create an TA from WSAA
	 *
	 * Request to WSAA for a tokent authorization for service and save this
	 * in a xml file
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs creating token authorization
	 *
	 * @return true if token authorization is created success
	**/
	private function CreateServiceTA($service)
	{
		// Only one process at a time renews the TA of a CUIT and service,
		// WSAA rejects a second request if there is already a valid TA
		$lock = @fopen($this->TA_FOLDER.$this->GetServiceTAKey($service).'.lock', 'c');

		if ($lock !== FALSE)
			flock($lock, LOCK_EX);

		try {
			// Other process could have renewed it while we were waiting
			if ($lock !== FALSE && $this->ReadServiceTA($service) !== NULL)
				$result = TRUE;
			else
				$result = $this->RequestServiceTA($service);
		} catch (Exception $e) {
			if ($lock !== FALSE) {
				flock($lock, LOCK_UN);
				fclose($lock);
			}

			throw $e;
		}

		if ($lock !== FALSE) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}

		return $result;
	}

	/**
	 * Request a TA to WSAA and store it
	 *
	 * @since 7.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs creating token authorization
	 *
	 * @return bool true if token authorization is created success
	**/
	private function RequestServiceTA($service)
	{
		//Creating TRA (with unique temporary files, TRA-CUIT-service names
		//would be overwritten by concurrent requests)
		$TRA = new SimpleXMLElement(
		'<?xml version="1.0" encoding="UTF-8"?>' .
		'<loginTicketRequest version="1.0">'.
		'</loginTicketRequest>');
		$TRA->addChild('header');
		$TRA->header->addChild('uniqueId',date('U'));
		$TRA->header->addChild('generationTime',date('c',date('U')-600));
		$TRA->header->addChild('expirationTime',date('c',date('U')+600));
		$TRA->addChild('service',$service);
		$tra_file = tempnam($this->TA_FOLDER, 'TRA-'.$this->options['CUIT'].'-'.$service.'-');
		$cms_file = tempnam($this->TA_FOLDER, 'TRA-'.$this->options['CUIT'].'-'.$service.'-');
		$TRA->asXML($tra_file);

		//Signing TRA
		$STATUS = openssl_pkcs7_sign($tra_file, $cms_file, "file://".$this->CERT,
			array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
			array(),
			!PKCS7_DETACHED
		);
		if (!$STATUS) {
			@unlink($tra_file);
			@unlink($cms_file);
			return FALSE;
		}
		$inf = fopen($cms_file, "r");
		$i = 0;
		$CMS="";
		while (!feof($inf)) {
			$buffer=fgets($inf);
			if ( $i++ >= 4 ) {$CMS.=$buffer;}
		}
		fclose($inf);
		@unlink($tra_file);
		@unlink($cms_file);

		//Request TA to WSAA
		$client = new SoapClient($this->WSAA_WSDL, $this->ApplySoapTimeout(array(
		'soap_version'   => SOAP_1_2,
		'location'       => $this->WSAA_URL,
		'trace'          => 1,
		'exceptions'     => 0
		)));
		$previous_timeout = $this->SetSocketTimeout();
		$results=$client->loginCms(array('in0'=>$CMS));
		$this->RestoreSocketTimeout($previous_timeout);
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);

		$TA = $results->loginCmsReturn;

		$key = $this->GetServiceTAKey($service);

		if (isset($this->options['ta_put']) && is_callable($this->options['ta_put'])) {
			call_user_func($this->options['ta_put'], $key, $TA);

			return TRUE;
		}

		// Atomic write: other processes read the TA without lock
		$file 	= $this->TA_FOLDER.$key.'.xml';
		$tmp 	= tempnam($this->TA_FOLDER, $key.'-');

		if ($tmp !== FALSE && file_put_contents($tmp, $TA)) {
			@chmod($tmp, 0666 & ~umask());

			if (@rename($tmp, $file))
				return TRUE;

			@unlink($tmp);
		}

		if (file_put_contents($file, $TA))
			return TRUE;
		else
			throw new Exception('Error writing "TA-'.$this->options['CUIT'].'-'.$service.'.xml"', 5);
	}

	public function __get($property)
	{
		if (in_array($property, $this->implemented_ws)) {
			if (isset($this->{$property})) {
				return $this->{$property};
			} else {
				$file = __DIR__.'/Class/'.$property.'.php';
				if (!file_exists($file)) 
					throw new Exception("Failed to open ".$file."\n", 1);

				include_once $file;

				return ($this->{$property} = new $property($this));
			}
		} else {
			return $this->{$property};
		}
	}
}

/**
 * Token Autorization
 *
 * @since 0.1
 *
 * @package Afip
 * @author 	Afip SDK afipsdk@gmail.com
 **/
class TokenAutorization {
	/**
	 * Authorization and authentication web service Token
	 *
	 * @var string
	 **/
	var $token;

	/**
	 * Authorization and authentication web service Sign
	 *
	 * @var string
	 **/
	var $sign;

	function __construct($token, $sign)
	{
		$this->token 	= $token;
		$this->sign 	= $sign;
	}
}

/**
 * Base class for AFIP web services 
 *
 * @since 0.5
 *
 * @package Afip
 * @author 	Afip SDK afipsdk@gmail.com
**/
class AfipWebService
{
	/**
	 * Web service SOAP version
	 *
	 * @var intenger
	 **/
	var $soap_version;

	/**
	 * File name for the Web Services Description Language
	 *
	 * @var string
	 **/
	var $WSDL;
	
	/**
	 * The url to web service
	 *
	 * @var string
	 **/
	var $URL;

	/**
	 * File name for the Web Services Description 
	 * Language in test mode
	 *
	 * @var string
	 **/
	var $WSDL_TEST;

	/**
	 * The url to web service in test mode
	 *
	 * @var string
	 **/
	var $URL_TEST;
	
	/**
	 * The Afip parent Class
	 *
	 * @var Afip
	 **/
	var $afip;

	/**
	 * SOAP client, created on the first request
	 *
	 * @var SoapClient
	 **/
	var $soap_client;

	function __construct($afip)
	{
		$this->afip = $afip;

		if ($this->afip->options['production'] === TRUE) {
			$this->WSDL = __DIR__.'/Afip_res/'.$this->WSDL;
		} else {
			$this->WSDL = __DIR__.'/Afip_res/'.$this->WSDL_TEST;
			$this->URL 	= $this->URL_TEST;
		}

		if (!file_exists($this->WSDL)) 
			throw new Exception("Failed to open ".$this->WSDL."\n", 3);
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($operation, $params = array())
	{
		if (!isset($this->soap_client)) {
			$cafile = isset($this->afip->options['cafile']) ? $this->afip->options['cafile'] : '/etc/ssl/certs/ca-certificates.crt';

			$options = $this->afip->ApplySoapTimeout([
                'soap_version' 	=> $this->soap_version,
                'location' 		=> $this->URL,
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ], [
                'ssl' => [
                    'ciphers' => 'DEFAULT:@SECLEVEL=1',
                    'cafile' => $cafile,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ]
            ]);

			$this->soap_client = new SoapClient($this->WSDL, $options);
		}

		$previous_timeout = $this->afip->SetSocketTimeout();

		try {
			$results = $this->soap_client->{$operation}($params);
		} catch (Exception $e) {
			$this->afip->RestoreSocketTimeout($previous_timeout);
			throw $e;
		}

		$this->afip->RestoreSocketTimeout($previous_timeout);

		$this->_CheckErrors($operation, $results);

		return $results;
	}

	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to check 
	 * @param mixed 	$results 	AFIP response
	 *
	 * @throws Exception if exists an error in response 
	 * 
	 * @return void 
	 **/
	private function _CheckErrors($operation, $results)
	{
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);
	}
}
