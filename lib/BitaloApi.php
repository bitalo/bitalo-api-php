<?php

// Bitalo API client class
class BitaloApi 
{
    // Bitalo API endpoints
    const API_URL = "http://local.bitalo.com/api/1";
    const AUTH_URL = "http://local.bitalo.com/auth";

    // Error codes from API
    const ERROR_AUTH_DENIED = 21;
    const ERROR_AUTH_CODE_INVALID = 22;
    const ERROR_AUTH_CLIENT_INVALID = 23;
    const ERROR_AUTH_TOKEN_INVALID = 24;
    const ERROR_AUTH_TOKEN_EXPIRED = 25;
    const ERROR_AUTH_FAILED = 26;
    const ERROR_API = 27;
    const ERROR_REFRESH_TOKEN_INVALID = 28;

    // User Agent string for sending requests
    public $ua_string = "BitaloApi/v1.0";

    // Timeout for API requests
    public $request_timeout = 10;

    // If the class should try to refresh tokens automatically
    public $auto_token_refresh = true;

    // Function called upon token refresh
    public $refresh_callback;

    // Client (application) ID
    private $client_id = "";

    // Client secret used to authenticate requests
    private $client_secret = "";

    // Debug mode (doesn't use strict SSL checks) - DON'T USE IN PRODUCTION
    private $debug = true;

    // Currently used access token
    private $access_token;

    // Currently used refresh token
    private $refresh_token;

    /**
     * Bitalo API class constructor
     * @param string $id Client (application) ID
     * @param string $secret Client secret
     * @param boolean $setup_state If we should generate 'state' parameter and store it in session
     */
    public function __construct($id, $secret, $setup_state = false) 
    {
        $this->client_id = $id;
        $this->client_secret = $secret;

        // If requested, setup "state" variable for auth flow
        if ($setup_state && !isset($_SESSION['state'])) {
            $_SESSION['state'] = str_shuffle(hash("sha256", microtime()));
        }
    }

    /**
     * Makes a request to Bitalo API
     * @param string $method HTTP method, one of: GET, POST, PUT, DELETE
     * @param string $url URL to be requested
     * @param array $data Data to be sent (optional)
     * @param boolean $resent Whether the request was resent due to expired token
     * @returns string parsed JSON response
     */
    public function request($method, $url, $data = array(), $resent = false) 
    {
        // Check if method is allowed
        $allowed_methods = ["GET", "POST", "PUT", "DELETE"];
        if (!in_array($method, $allowed_methods)) {
            throw new BitaloApi_Exception("Method not allowed", BitaloApi_Exception::REQUEST_INVALID);
        }

        // Set up cURL options array
        $curl_options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->request_timeout,
        ];

        // Set up SSL cert and options (depending on whether debug option is on)
        if (!$this->debug) {
            $curl_options[CURLOPT_CAINFO] = dirname(__FILE__) . '/bitalo-ca.crt';
        }
        else {
            $curl_options[CURLOPT_SSL_VERIFYHOST] = 0;
            $curl_options[CURLOPT_SSL_VERIFYPEER] = 0;
        }

        // Set up HTTP headers array
        $http_headers = [
            "User-Agent: " . $this->ua_string,
        ];

        // Append Authorization header if token is present
        if ($this->access_token) {
            $http_headers[] = "Authorization: Bearer " . $this->access_token;
        }
        $curl_options[CURLOPT_HTTPHEADER] = $http_headers;

        // Build URL depending on method and data
        $request_url = $this->buildURL($method, $url, $data);
        $query = http_build_query($data);
        $curl_options[CURLOPT_URL] = $request_url;

        // Handle different HTTP methods
        switch ($method) {
            case "GET":
                $curl_options[CURLOPT_HTTPGET] = true;
                break;

            case "POST":
                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = $query;
                break;

            case "DELETE":
                $curl_options[CURLOPT_POSTFIELDS] = $query;

            case "PUT":
                $curl_options[CURLOPT_CUSTOMREQUEST] = $method;
                break;
        }

        // Make cURL request
        $curl = curl_init();
        curl_setopt_array($curl, $curl_options);
        $response = curl_exec($curl);

        // Check for errors
        if ($response === false) 
        {
            $errmsg = curl_error($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            throw new BitaloApi_Exception("Request failed with error (" . $errno . "): " . $errmsg,
                BitaloApi_Exception::REQUEST_CURL);
        }

        // Decode response
        $json = json_decode($response);
        if ($json === NULL) {
            throw new BitaloApi_Exception("Cannot decode JSON response in request $url: " . $response,
                BitaloApi_Exception::REQUEST_DECODE);
        }

        // If we get a "token expired" response and token auto refresh is enabled
        // we issue a refresh token request and re-send the original request. If that fails again,
        // no further attempts are made. A refresh callback function has to be defined before.
        if ($json->status == "error" && $json->code == BitaloApi::ERROR_AUTH_TOKEN_EXPIRED
            && $this->auto_token_refresh == true && isset($this->refresh_token) && !$resent
            && isset($this->refresh_callback) && is_callable($this->refresh_callback)) {
            curl_close($curl);

            $this->refreshTokens();
            return $this->request($method, $url, $data, TRUE);
        }

        // Close the curl handle and return the data
        curl_close($curl);
        return $json;
    }

    /**
     * Stores the tokens for later requests
     * @param string $access_token Access token
     * @param string $refresh_token Refresh token (optional)
     */
    public function setTokens($access_token, $refresh_token = "")
    {
        $this->access_token = $access_token;
        if ($refresh_token) {
            $this->refresh_token = $refresh_token;
        }
    }

    /**
     * Fetches access token in exchange for authorization code
     * @param string $code Authorization code
     * @returns array access_token, refresh_token, expires_in, token_type, scope, user_id
     */
    public function getAuthToken($code) 
    {
        $response = $this->request("POST", self::AUTH_URL . "/token/", [
            "grant_type" => "authorization_code",
            "code" => $code,
            "client_id" => $this->client_id,
            "client_secret" => $this->client_secret
        ]);

        if ($response && $response->status == "ok") {
            $this->setTokens($response->access_token, $response->refresh_token);
        }
        else {
            throw new BitaloApi_Exception("Getting token failed. Server response: " . 
                print_r($response, true), BitaloApi_Exception::AUTH_ERROR);
        }

        return $response;
    }

    /**
     * Fetches a fresh access token in exchange for refresh token
     * @param string $refresh_token Refresh token (optional if already set in instance variable)
     * @returns array access_token, refresh_token, expires_in, token_type, scope, user_id
     */
    public function refreshTokens($refresh_token = "")
    {
        // Check for refresh token
        if (!$refresh_token) {
            if (isset($this->refresh_token)) {
                $refresh_token = $this->refresh_token;
            }
            else {
                throw new BitaloApi_Exception("Refresh token is required for calling refreshTokens()", 
                    BitaloApi_Exception::TOKEN_MISSING);
            }
        }

        $response = $this->request("POST", self::AUTH_URL . "/token/", [
            "grant_type" => "refresh_token",
            "refresh_token" => $refresh_token,
            "client_id" => $this->client_id,
            "client_secret" => $this->client_secret
        ]);

        if ($response && $response->status == "ok") {
            $this->setTokens($response->access_token, $response->refresh_token);

            // Call the refresh callback function if available
            if (isset($this->refresh_callback) && is_callable($this->refresh_callback)) {
                call_user_func_array($this->refresh_callback, 
                    array($response->access_token, $response->refresh_token));
            }
        }

        return $response;
    }

    /**
     * Checks if the state variable gotten from the request matches the one stored in session
     * @returns boolean True check succeeded, False if not
     */
    public function checkAuthState() {
        return (isset($_GET['state']) && $_SESSION['state'] == $_GET['state']);
    }

    /**
     * Fetches user profile details
     * @returns array "profile" array containing profile details
     */
    public function getUserProfile() 
    {
        // Token is required for this method
        if (!$this->access_token) {
            throw new BitaloApi_Exception("Token is required for calling " . __FUNCTION__, 
                BitaloApi_Exception::TOKEN_MISSING);
        }

        return $this->request("GET", self::API_URL . "/user/profile/");
    }

    /**
     * Creates a new Bitcoin payment
     * @param string address Bitcoin address to sent the payment to
     * @param double amount Amount of Bitcoin to be sent
     * @param string callback URL of script that will be called when payment is complete
     * @returns array response containing payment_id
     */
    public function createNewPayment($address, $amount, $callback) {
        return $this->request("POST", self::API_URL . "/payment", [
            "address" => $address,
            "amount" => $amount,
            "callback" => $callback,
            "client_id" => $this->client_id,
            "client_secret" => $this->client_secret
        ]);
    }

    /**
     * Gets information about a payment
     * @param string payment_id Payment identifier
     * @returns array response containing payment details
     */
    public function getPaymentInfo($payment_id) {
        return $this->request("GET", self::API_URL . "/payment/" . $payment_id, [
            "client_id" => $this->client_id,
            "client_secret" => $this->client_secret
        ]);
    }

    /**
     * Appends query string to the URL when HTTP method requires it
     * @param string $method HTTP method
     * @param string $url URL to be requested
     * @param array $data Data to be sent
     */
    private function buildURL($method, $url, $data) 
    {
        $query = http_build_query($data);

        if ($query) {
            if ($method == "GET" || $method == "DELETE") {
                $url .= "?" . $query;
            }
        }
        return $url;
    }

}

// Bitalo API client custom exception class
class BitaloApi_Exception extends Exception 
{
    const REQUEST_INVALID = 101;   
    const REQUEST_CURL = 102;
    const REQUEST_DECODE = 103;
    const TOKEN_MISSING = 104;
    const AUTH_ERROR = 105;
}
