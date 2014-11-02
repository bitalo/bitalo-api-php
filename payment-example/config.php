<?php

// Fill in your application credentials below
// Note: Client ID and (especially) secret should be stored 
// in a separate, non version-controlled file
define("API_CLIENT_ID", "F1LYjqSSG1C2kRFU");
define("API_CLIENT_SECRET", "pFwEiNsvwBytXyygpBRzN2iyf9QwjJVB");

// Bitcoin address which will receive the payment
// Note: In production, this address should be automatically 
// generated for each transaction
define("BTC_ADDRESS", "miPEAK2jsnVhfYdmw5JwEKR9PtvgFtxfxJ");

// URL to your callback script, that will be called upon successful payment
define("CALLBACK", "http://local.market.bitalo.com/callback.php");

// URL to Bitalo payment endpoint
define("BITALO_PAYMENT_URL", "http://local.bitalo.com/pay/");

// Product prices
$prices = [
    1 => 15.99,
    2 => 2.4501,
    3 => 0.055
];
