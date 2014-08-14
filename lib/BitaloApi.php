<?php

// Bitalo API client class
class BitaloApi 
{
    // Bitalo API endpoints
    const API_URL = "http://bitalo.com/api/1";
    const AUTH_URL = "http://bitalo.com/auth";

    // User Agent string for sending requests
    public $ua_string = "BitaloApi/v1.0";

    // Timeout for API requests
    public $request_timeout = 10;

    // Client secret used to authenticate requests
    private $client_secret = "";

    // Debug mode (doesn't use strict SSL checks)
    private $debug = false;

    // Currently used access token
    private $access_token;

    /**
     * Bitalo API class constructor
     * @param string $id Client (application) ID
     * @param string $secret Client secret
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
     * @param array $data Data to be sent
     * @returns string parsed JSON response
     */
    public function request($method, $url, $data = array()) 
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
        $url = $this->buildURL($method, $url, $data);
        $query = http_build_query($data);
        $curl_options[CURLOPT_URL] = $url;

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
            throw new BitaloApi_Exception("Cannot decode JSON response",
                BitaloApi_Exception::REQUEST_DECODE);
        }

        // Close the curl handle and return the data
        curl_close($curl);
        return $json;
    }

    /**
     * Fetches access token in exchange for authorization code
     * @param string $code Authorization code
     * @returns array access_token, expires_in, token_type, scope, user_id
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
            $this->access_token = $response->access_token;
        }
        else {
            throw new BitaloApi_Exception("Getting token failed. Server response: " . 
                print_r($response, true), BitaloApi_Exception::AUTH_ERROR);
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
