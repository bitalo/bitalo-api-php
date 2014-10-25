<?php

/**
 * Bitalo Auth API example
 *
 * A simple page to show how to integrate Bitalo Auth API
 * and fetch user account details.
 *
 * @author Maciej Trębacz <maciej@bitalo.com>
 */



// API credentials
define("API_CLIENT_ID", "kegSI2kM9krE1Rpd");
define("API_CLIENT_SECRET", "ZQdLlUfRGKHq19yhnGV6cO0XwoUqcJxz");

// Start a session for storing user state
session_start();

// Include and setup Bitalo API library
require("../lib/Bitalo/Api/BaseClient.php");
require("../lib/Bitalo/Api/Client.php");
require("../lib/Bitalo/Api/Exception.php");


$api = new Bitalo\Api\Client(API_CLIENT_ID, API_CLIENT_SECRET, true);

// Setup a function to store tokens for further usage
$store_tokens = function ($access_token,$refresh_token) {
	// Normally normally should be stored in the DB, but for the sake of the example we use sessions
	$_SESSION['access_token'] = $access_token;
	$_SESSION['refresh_token'] = $refresh_token;
};

// Bind the storage function to Bitalo API class (needed for auto token refresh)
$api->refresh_callback = $store_tokens;

// Setup tokens for further API requests
if(isset($_SESSION['access_token'])) {
	$api->setTokens($_SESSION['access_token'],$_SESSION['refresh_token']);
}

// Handling user logout
if(isset($_GET['logout'])) {
	unset($_SESSION['user']);
	unset($_SESSION['access_token']);
	unset($_SESSION['refresh_token']);
	header("Location: /");
}

// Handle API callback for logging in
if(isset($_GET['login'])) {
	// Necessary to prevent auth flow hijack!
	$api->checkAuthState() or die("Error: State property invalid or missing! " . $_SESSION['state']);

	// Get the access token
	$token_response = $api->getAuthToken($_GET['access_code']);

	// Get user profile
	$user_response = $api->getUserProfile();

	// Verify if someone isn't trying to impersonate another user (important!)
	if($token_response->user_id != $user_response->profile->user_id) {
		die("User ID mismatch.");
	}

	// Store username in the session (should store user ID, but this is only an example)
	if($user_response->status == "ok") {
		$_SESSION['user'] = $user_response->profile->username;

		// Store tokens for later
		$store_tokens($token_response->access_token,$token_response->refresh_token);
	}

	// Output Javascript to reload parent window and close auth popup
	echo "<script> window.opener.location.reload(); window.close(); </script>";
	exit;
}

// Handle sample API request
if(isset($_GET['request'])) {
	$request_response = $api->getUserProfile();
}

// Handle token refresh request
if(isset($_GET['refresh'])) {
	$request_response = $api->refreshTokens();

	$store_tokens($request_response->access_token,$request_response->refresh_token);
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Bitalo API Example</title>

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>

	<script>
		jQuery(document).ready(function () {
			$('#login-btn').on('click', function () {
				var authWindow = window.open("http://bitalo.com/auth/?response_type=code&client_id=<?php echo API_CLIENT_ID; ?>&state=<?php echo $_SESSION['state']; ?>", "", "width=680, height=500");
			});
		});
	</script>
</head>
<body>
<div class="container theme-showcase" role="main">
	<br>

	<div class="jumbotron">
		<h1>Bitalo API example</h1>

		<p>This is an example of how to log in to other Bitalo sites using Bitalo API.</p>
		<?php if(!isset($_SESSION['user'])) { ?>
			<p>You are not logged in. Click on the button below to log in.</p>
			<p><a href="#" class="btn btn-primary btn-lg" id="login-btn" role="button">Sign in to Bitalo</a></p>
		<?php } else { ?>
			<p>Welcome, <b><?php echo $_SESSION['user']; ?></b>. <a href="/?logout=1">Logout</a></p>
			<p><a href="?request=1">Make an API request</a> | <a href="?refresh=1">Refresh access token</a></p>
			<?php if(isset($request_response)) { ?>
				<p>API Request Response:</p>
				<p><?php var_dump($request_response); ?></p>
			<?php } else {
				if(isset($refresh_response)) {
					?>
					<p>Token Response:</p>
					<p><?php var_dump($refresh_response); ?></p>
				<?php
				}
			} ?>
		<?php } ?>
	</div>
</div>
</body>
</html>
