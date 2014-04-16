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
	 
	/**
	 * Load Folders.
	 * 
	 * Loads all messages and contacts into separate collections for each folder, and stores in the view.
	 *
	 * @param MsView $viewObject
	 * @return Bool	False if there were issues with the init, otherwise true.
	 */
	 protected function _loadFolders(&$viewObject) {
	 	// load all contacts and messages for current user
		$inboxArray = $this->db->call("messages('" . $this->user->getValue('id') . "','received','0','999999')");
		$sentArray = $this->db->call("messages('" . $this->user->getValue('id') . "','sent','0','999999')");
		$importantArray = $this->db->call("messages('" . $this->user->getValue('id') . "','important','0','999999')");
		$draftsArray = $this->db->call("messages('" . $this->user->getValue('id') . "','draft','0','999999')");
		$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
		
		$inbox = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
		$inbox->load($inboxArray);
		$sent = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
		$sent->load($sentArray);
		$important = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
		$important->load($importantArray);
		$drafts = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
		$drafts->load($draftsArray);

		$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
		$contacts->load($contactsArray);
		
		
		// store data in view
		$viewObject->setValue('inbox', $inbox);
		$viewObject->setValue('sent', $sent);
		$viewObject->setValue('important', $important);
		$viewObject->setValue('drafts', $drafts);
		$viewObject->setValue('contacts', $contacts);
	 }
	 
}