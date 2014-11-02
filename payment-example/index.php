<?php

/**
 * Bitalo Payments API example
 *
 * A sample implementation of Bitalo Payments API
 * in a shop-like web application.
 *
 * @author Maciej Trębacz <maciej@trebacz.org>
 */

// Get product data
require "config.php";

?><!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Bitalo Payments Example</title>

        <!-- Latest compiled and minified CSS -->
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">

        <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>

        <!-- Latest compiled and minified JavaScript -->
        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
        
        <script>
        jQuery(document).ready(function () {
            $('.buy-btn').on('click', function() {
                var payWindow = window.open("pay.php?id=" + $(this).data('item'), "", "width=680, height=500");
            });
        });
        </script>
    </head>
    <body>
    <div class="container theme-showcase" role="main">
      <br>
      <div class="jumbotron">
        <h1>Bitalo Payments example</h1>
        <p>This website showcases how to use Bitalo Payments API. Please pick one of the products below.</p>
        <p>Please note that this is a very simple example, normally you would have payment button on a separate page with all payment details.</p>
      </div>
        <div class="row">
        <div class="col-md-4">
          <h2>ASIC 5 PH/s</h2>
          <p>The newest version of ASIC Miner, that will quadruple your mining profits</p>
          <p><strong>Price:</strong> <?=$prices[1];?> BTC</p>
          <p><a class="btn btn-default buy-btn" data-item="1" href="#" role="button">Buy now »</a></p>
        </div>
        <div class="col-md-4">
          <h2>Electric toothbrush</h2>
          <p>Your teeth were never so clean before. Our patent pending formula will disintegrate every remaining bit of food from your gums.</p>
          <p><strong>Price:</strong> <?=$prices[2];?> BTC</p>
          <p><a class="btn btn-default buy-btn" data-item="2" href="#" role="button">Buy now »</a></p>
        </div>
        <div class="col-md-4">
          <h2>USB cup heater</h2>
          <p>Everybody hates when their hot tea is turning cold while watching the charts. This cup heater will last you for every rally. </p>
          <p><strong>Price:</strong> <?=$prices[3];?> BTC</p>
          <p><a class="btn btn-default buy-btn" data-item="3" href="#" role="button">Buy now »</a></p>
        </div>
      </div>
    </div>
    </body>
</html>
