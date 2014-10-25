<?php

namespace Bitalo\Api;

/**
 * Class Client
 *
 * @package  Bitalo\Api
 *
 * @author   Maciej Trębacz <maciej@bitalo.con>
 * @author   Peter Limbach <peter@bitalo.com>
 */
abstract class BaseClient {

	/**
	 * API Error Codes
	 */
	const ERROR_AUTH_DENIED = 21;
	const ERROR_AUTH_CODE_INVALID = 22;
	const ERROR_AUTH_CLIENT_INVALID = 23;
	const ERROR_AUTH_TOKEN_INVALID = 24;
	const ERROR_AUTH_TOKEN_EXPIRED = 25;
	const ERROR_AUTH_FAILED = 26;
	const ERROR_API = 27;
	const ERROR_REFRESH_TOKEN_INVALID = 28;

	/**
	 * API Endpoints
	 *
	 * @const string
	 */
	const API_URL  = 'https://bitalo.com/api/1';

	/**
	 * Auth Endpoint
	 *
	 * @const string
	 */
	const AUTH_URL = 'https://bitalo.com/auth';

	/**
	 * OK
	 *
	 * @const string
	 */
	const STATUS_OK = 'ok';

	/**
	 * ERROR
	 *
	 * @const string
	 */
	const STATUS_ERROR = 'error';

	/**
	 * GrantType Authorization Code
	 *
	 * @const string
	 */
	const GRANT_TYPE_AUTHORIZATION_CODE  = 'authorization_code';

	/**
	 * GrantType RefreshCode
	 *
	 * @const string
	 */
	const GRANT_TYPE_REFRESH_CODE = 'refresh_token';

	/**
	 * default configuration
	 *
	 * @var array
	 */
	protected $configuration = array(
		'ua_string'          => 'BitaloApi/v1.0',
		'$request_timeout'   => 10,
		'auto_token_refresh' => true,
		'refresh_callback'   => true,
		'debug'              => false,
		'request_methods'    => array('GET', 'POST', 'PUT', 'DELETE')
	);





	// Function called upon token refresh
	public $refresh_callback;

	// Client (application) ID
	private $client_id = '';

	// Client secret used to authenticate requests
	private $client_secret = '';

	// Debug mode (doesn't use strict SSL checks) - DON'T USE IN PRODUCTION
	private $debug = false;

	// Currently used access token
	private $access_token;

	// Currently used refresh token
	private $refresh_token;

	/**
	 * Bitalo API class constructor
	 *
	 * @param string $id     Client (application) ID
	 * @param string $secret Client secret
	 * @param array  $configuration
	 */
	public function __construct($id, $secret, array $configuration = array()) {
		$this->configuration = array_merge($this->configuration, $configuration);

		$this->client_id = $id;
		$this->client_secret = $secret;
	}

	/**
	 * Makes a request to Bitalo API
	 *
	 * @param string  $method HTTP method, one of: GET, POST, PUT, DELETE
	 * @param string  $url    URL to be requested
	 * @param array   $data   Data to be sent (optional)
	 * @param boolean $resent Whether the request was resent due to expired token
	 *
	 * @throws Exception
	 * @returns \Object
	 */
	public function request($method, $url, $data = array(), $resent = false) {

		// Check if method is allowed
		if(!in_array($method, $this->configuration['request_methods'])) {
			throw new Exception(Exception::REQUEST_INVALID, $method);
		}

		// Set up cURL options array
		$curl_options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->configuration['request_timeout'],
		];

		// Set up SSL cert and options (depending on whether debug option is on)
		if(!$this->debug) {
			$curl_options[CURLOPT_CAINFO] = __DIR__ .  '/bitalo-ca.crt';
		} else {
			$curl_options[CURLOPT_SSL_VERIFYHOST] = 0;
			$curl_options[CURLOPT_SSL_VERIFYPEER] = 0;
		}

		// Set up HTTP headers array
		$http_headers = array(sprintf('User-Agent: %s', $this->configuration['ua_string']));


		// Append Authorization header if token is present
		if($this->access_token) {
			$http_headers[] = 'Authorization: Bearer ' . $this->access_token;
		}
		$curl_options[CURLOPT_HTTPHEADER] = $http_headers;

		// Build URL depending on method and data
		$curl_options[CURLOPT_URL] = $this->buildURL($method, $url, $data);

		// Handle different HTTP methods
		switch($method) {
			case 'GET':
				$curl_options[CURLOPT_HTTPGET] = true;
				break;

			case 'POST':
				$curl_options[CURLOPT_POST] = true;
				$curl_options[CURLOPT_POSTFIELDS] =  http_build_query($data);
				break;

			case 'DELETE':
				$curl_options[CURLOPT_POSTFIELDS] =  http_build_query($data);
				$curl_options[CURLOPT_CUSTOMREQUEST] = $method;

				break;
			case 'PUT':
				$curl_options[CURLOPT_CUSTOMREQUEST] = $method;
				break;
		}

		// Make cURL request
		$curl = curl_init();
		curl_setopt_array($curl, $curl_options);
		$response = curl_exec($curl);

		// Check for errors
		if($response === false) {
			curl_close($curl);

			throw new Exception(Exception::REQUEST_CURL, array(
				curl_errno($curl), curl_error($curl)
			));
		} else {
			if(null === $response = json_decode($response)) {
				throw new Exception(Exception::REQUEST_DECODE);
			}
		}

		// If we get a 'token expired' response and token auto refresh is enabled
		// we issue a refresh token request and re-send the original request. If that fails again,
		// no further attempts are made. A refresh callback function has to be defined before.

		if($response->status == self::STATUS_ERROR && $response->code == self::ERROR_AUTH_TOKEN_EXPIRED &&
			$this->configuration['auto_token_refresh'] && isset($this->refresh_token) && !$resent
			&& isset($this->refresh_callback) && is_callable($this->refresh_callback)) {
			curl_close($curl);

			$this->refreshTokens();
			return $this->request($method, $url, $data, true);
		}

		curl_close($curl);
		return $response;
	}

	/**
	 * Stores the tokens for later requests
	 *
	 * @param string $access_token  Access token
	 * @param string $refresh_token Refresh token (optional)
	 */
	public function setTokens($access_token, $refresh_token = '') {
		$this->access_token = $access_token;

		if($refresh_token) {
			$this->refresh_token = $refresh_token;
		}
	}

	/**
	 * Fetches access token in exchange for authorization code
	 *
	 * @param string $code Authorization code
	 *
	 * @throws Exception
	 * @returns array access_token, refresh_token, expires_in, token_type, scope, user_id
	 */
	public function getAuthToken($code) {
		$response = $this->request('POST', self::AUTH_URL . '/token/', array(
			'grant_type'    => self::GRANT_TYPE_AUTHORIZATION_CODE,
			'code'          => $code,
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret));

		if($response && $response->status == self::STATUS_OK) {
			$this->setTokens($response->access_token, $response->refresh_token);
		} else {
			throw new Exception(Exception::AUTH_ERROR, $response);
		}

		return $response;
	}

	/**
	 * Fetches a fresh access token in exchange for refresh token
	 *
	 * @param string $refresh_token Refresh token (optional if already set in instance variable)
	 *
	 * @throws Exception
	 * @returns array access_token, refresh_token, expires_in, token_type, scope, user_id
	 */
	public function refreshTokens($refresh_token = '') {
		// Check for refresh token
		if(!$refresh_token) {
			if(isset($this->refresh_token)) {
				$refresh_token = $this->refresh_token;
			} else {
				throw new Exception(Exception::TOKEN_MISSING);
			}
		}

		$response = $this->request('POST', self::AUTH_URL . '/token/',array(
			'grant_type'    => self::GRANT_TYPE_AUTHORIZATION_CODE,
			'refresh_token' => $refresh_token,
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret
		));

		if($response && $response->status == self::STATUS_OK) {
			$this->setTokens($response->access_token,$response->refresh_token);

			// Call the refresh callback function if available
			if(isset($this->refresh_callback) && is_callable($this->refresh_callback)) {
				call_user_func_array($this->refresh_callback,
					array($response->access_token,$response->refresh_token));
			}
		}

		return $response;
	}

	/**
	 * Checks if the state variable gotten from the request matches the one stored in session
	 *
	 * @returns boolean True check succeeded, False if not
	 */
	abstract public function checkAuthState();

	/**
	 * Fetches user profile details
	 *
	 * @throws Exception
	 * @returns array 'profile' array containing profile details
	 */
	public function getUserProfile() {
		// Token is required for this method
		if(!$this->access_token) {
			throw new Exception(Exception::TOKEN_MISSING);
		}

		return $this->request('GET', self::API_URL . '/user/profile/');
	}

	/**
	 * Appends query string to the URL when HTTP method requires it
	 *
	 * @param string $method HTTP method
	 * @param string $url    URL to be requested
	 * @param array  $data   Data to be sent
	 *
	 * @return string
	 */
	private function buildURL($method,$url,$data) {
		$query = http_build_query($data);

		if($query) {
			if($method == 'GET' || $method == 'DELETE') {
				$url .= '?' . $query;
			}
		}
		return $url;
	}
}