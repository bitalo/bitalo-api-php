<?php

namespace Bitalo\Api;

class Client extends BaseClient {

	/**
	 * Bitalo API class constructor
	 *
	 * @param string $id     Client (application) ID
	 * @param string $secret Client secret
	 * @param bool   $setup_state
	 */
	public function __construct($id,$secret,$setup_state = false) {
		parent::__construct($id, $secret);

		// If requested, setup "state" variable for auth flow
		if($setup_state && !isset($_SESSION['state'])) {
			$_SESSION['state'] = str_shuffle(hash("sha256",microtime()));
		}
	}

	/**
	 * Checks if the state variable gotten from the request matches the one stored in session
	 *
	 * @returns boolean True check succeeded, False if not
	 */
	public function checkAuthState() {
		return (isset($_GET['state']) && $_SESSION['state'] == $_GET['state']);
	}
}