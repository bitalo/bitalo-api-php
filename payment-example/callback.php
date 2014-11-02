<?php

// Include configuration variables
require "config.php";

// Include Bitalo PHP API
require "../lib/BitaloApi.php";

$api = new BitaloApi(API_CLIENT_ID, API_CLIENT_SECRET, TRUE); 

// Incoming request
if ($_POST['payment_id']) 
{
    if($_POST['status'] == "complete") 
    {
        // Get payment details to verify it
        $request = $api->getPaymentInfo($_POST['payment_id']);
        if ($request->status == "ok" && $request->payment->status == "complete") 
        {
            // Payment received and verified!
            // For testing purposes, store the response in a text file
            // so you can see that the callback worked
            file_put_contents("payment.txt", print_r($request, true));
        }
    }
}
