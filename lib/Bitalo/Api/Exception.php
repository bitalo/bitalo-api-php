<?php

namespace Bitalo\Api;


/**
 * Class ApiException
 *
 * @doc Bitalo API client custom exception class
 * @package Bitalo\Api
 */
class Exception extends \Exception {

	const REQUEST_INVALID = 101;

	const REQUEST_CURL    = 102;

	const REQUEST_DECODE  = 103;

	const TOKEN_MISSING   = 104;

	const AUTH_ERROR      = 105;

	/**
	 * @var array
	 */
	public static $ERROR_MESSAGES = array(

		self::REQUEST_INVALID  => 'Request method not allowed',

		self::REQUEST_CURL     => 'Request failed with ERROR[%s] = %s ',

		self::REQUEST_DECODE   => 'Cannot decode json response',

		self::TOKEN_MISSING    => 'No refresh token provided',

		self::AUTH_ERROR       => 'Authentication ERROR = %s'
	);

	/**
	 * @param string $code
	 * @param null   $parameters
	 */
	public function __construct($code, $parameters = null) {

		parent::__construct(vsprintf(self::$ERROR_MESSAGES[$code], (is_array($parameters)) ? $parameters : array($parameters)), $code);
	}

}
