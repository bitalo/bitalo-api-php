<?php

// Include configuration variables
require "config.php";

// Include Bitalo PHP API
require "../lib/BitaloApi.php";
$api = new BitaloApi(API_CLIENT_ID, API_CLIENT_SECRET, TRUE); 

// Get payment amount
$amount = $prices[intval($_GET['id'])];

// Send a request to create new payment
$payment_response = $api->createNewPayment(BTC_ADDRESS, $amount, CALLBACK);

if ($payment_response && $payment_response->status == "ok") {
    // Redirect user's browser to Bitalo for completing the payment
    header("Location: " . BITALO_PAYMENT_URL . $payment_response->payment_id);
}
else {
    // Handle error
    die("Error while communicating with API.<br>" . print_r($payment_response, true));
}
