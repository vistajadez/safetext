<?php

/**
 * SafeText Client Controller.
 *
 * Parent class for Safetext web client controllers, in order to reuse common init functionality.
 *
 *
 */
abstract class SafetextClientController extends MsController {
	
	protected $db;
	protected $user;
	
	/**
	 * Init.
	 * 
	 * Performs common initialization/auth functionality.
	 *
	 * @param MsView $viewObject
	 * @param String $pageTitle	(Optional) Defaults to product title.
	 * @return Bool	False if there were issues with the init, otherwise true.
	 */
	 public function init(&$viewObject, $pageTitle='' ) {
	 	// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			header("Location: $redirect");
				
			die();
		}
		
		// already init'd?
		if ($viewObject->getvalue('user') instanceof SafetextUser) return true;
		
		
		// Create a database connection to share
		if (!$this->db instanceof MsDb) {
			$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']); 
			$this->db = $db;
		}
		
		// Page title
		if ($pageTitle == '') $pageTitle = $this->config['productName'];
		$viewObject->setTitle($pageTitle);
		
		// Handle user authentication
		require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
		
		// Authenticate user
		if (array_key_exists('token', $_COOKIE)) {
			if ($_COOKIE['token'] != '') {
				require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
				$user = SafetextUser::tokenToUser($_COOKIE['token'], $this->db, $this->config);
				
				if ($user->isValid()) {
					// update auth cookie with device's token, in case it's changed
					setcookie("token", $user->getRelationship('device')->token, time()+60*60*24*7, '/');  /* expire in 7 days */
				
					// store user object in view
					$viewObject->setValue('user', $user);
					$this->user = $user;
					return true;
				}
			}
		}
		
		// unable to authenticate
		return false;
	 }
	 
}