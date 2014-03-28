<?php

/**
 * Controller for Webservices REST API version 1.
 *
 *
 * This is the controller for the SafeText Webservices REST API, version 1.
 *
 */
class ApiController extends MsController {
	
	/**
	 * Default Action.
	 * 
	 * Called when no action is defined.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
	 	
	 	// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		} else {	 
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'No action specified'));
		}
	 }
	 


	/**
	 * Auth Action.
	 * 
	 * SafeText is a secure application and it is important to authenticate all API calls with a unique token obtained from the server
	 * by providing a user's account username and password. This authentication step effectively logs the mobile app user into SafeText.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Authentication
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function authAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_USERNAME', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_USERNAME'] !== '') {
					if (array_key_exists('HTTP_X_SAFETEXT_PASSWORD', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_PASSWORD'] !== '') {
						if (array_key_exists('device_signature', $this->params) && $this->params['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->params['device_description'] !== '') {
								// Create a database connection to share
								$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
			
								// generate token via db stored procedure
								require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
								$tokenDetails = SafetextUser::generateToken($_SERVER['HTTP_X_SAFETEXT_USERNAME'], $_SERVER['HTTP_X_SAFETEXT_PASSWORD'], $this->params['device_signature'], $this->params['device_description'], $db, $this->config);
								
								if ($tokenDetails['id'] > 0) {
								
									$viewObject->setValue('status', 'success');
									$viewObject->setValue('data', array('token' => $tokenDetails['token'], 'user' => $tokenDetails['id']));
									
									// log the auth request
									$this->config['log']->write('User: ' . $tokenDetails['id'] . ', Token: ' . $tokenDetails['token'] . ' (' . $this->params['device_description'] . ')', 'Auth Request');
		
								} else { // unsuccessful auth token generation
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => $tokenDetails['msg']));
								}
							} else { // no device sig
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Missing device description'));
							}
						} else { // no device sig
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Missing device signature'));
						}
					} else { // no password
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing SafeText password'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText username'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for auth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Expire Auth Action.
	 * 
	 * When a user opts to logout, the server will expire the auth token and the mobile app should remove the auth token from the device 
	 * completely. The user will be required to login again (authenticate again) for the mobile app to make subsequent API calls.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Authentication
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function expireauthAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('token', $this->params) && $this->params['token'] !== '') {
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// expire token via db stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					SafetextUser::expireToken($this->params['token'], $db, $this->config);
				
					$viewObject->setValue('status', 'success');
					
					// log the expireauth request
					$this->config['log']->write('Token: ' . $tokenDetails['token'], 'Expire Auth Request');

				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing auth token'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for expireauth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
		
		
	/**
	 * Messages Action.
	 * 
	 * Messages are sent from one user to another using the Send Messages web service. 
	 * They will be received by the recipient during the recipient user's next device sync.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sending-Messages
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function messagesAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if (array_key_exists('recipients', $this->params) && is_array($this->params['recipients'])) {
								if (array_key_exists('content', $this->params) && is_array($this->params['content']) !== '') {
									// check all message options
									array_key_exists('is_important', $this->params)? $is_important = $this->params['is_important']: $is_important = '0';
									array_key_exists('is_draft', $this->params)? $is_draft = $this->params['is_draft']: $is_draft = '0';
									array_key_exists('lifetime', $this->params)? $lifetime = $this->params['lifetime']: $lifetime = '24';
									if ($lifetime > 24) $lifetime = 24; // message lifetime cannot be more than 24 hrs
									$content = $this->escapeForDb($this->params['content']);
									
									// log the send message request
									$this->config['log']->write('User: ' . $user->id . ": sendMessage('" . $user->id . "','" . current($this->params['recipients']) . "','" . $content . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "')", 'Send Message Request');
								
									// execute send via stored procedure
									$result = current($db->call("sendMessage('" . $user->id . "','" . current($this->params['recipients']) . "','" . $content . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "')"));
									
									if ($result['key'] > 0) {
										// load successful output into view
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('key' => $result['key']));
									} else {
										// load error message output into view
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('message' => $result['msg']));
									}
					
								} else { // no content
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('token', $user->getRelationship('device')->token);
									$viewObject->setValue('data', array('message' => 'Empty message'));
								}
							} else { // no recipients listed
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Recipient list should be passed as an array'));
							}
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Sync Action.
	 * 
	 * SafeText facilitates messaging between two users. When one user adds another user as a contact, messages can then be sent 
	 * between the two users. The SafeText web service maintains every user's contact list (the list of users added to their account 
	 * as contacts), as well as every user's active messages.
	 *
	 * When new users are added to a contact list on the web client or messages are sent from the web client, this information 
	 * needs to be sent to the appropriate users' mobile app on their device(s). Likewise, such updates that take place on the mobile app 
	 * need to be sent to the web service.
	 *
	 * Synchronization is a two-way protocol that is always initiated by the mobile app.
	 *
	 * Synchronization begins with the mobile app sending a sync request to the web client REST server. All records which have been 
	 * added, edited, or marked for deletion since the last sync should be passed in this request. The server then returns a JSON response 
	 * containing any records which have been added, edited, or deleted on the web client.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sync-Protocol
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function syncAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							// log the sync request
							$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), Data: ' . json_encode($this->params['data']), 'Sync Request');
						
							// execute sync (pass mobile device records to server and obtain records to send to mobile device)
							$recordsOut = $user->getRelationship('device')->sync($this->params['data']);
							// log the output
							if (sizeof($recordsOut) > 0) $this->config['log']->write('Records Out: ' . json_encode($recordsOut));

							// load output into view
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', $recordsOut);
					
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for sync'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Last Pull Action.
	 * 
	 * To re-request the previous sync response from the server (i.e. if the response was not processed properly for some reason, 
	 * such as connectivity interruption), it can easily be obtained using the following REST URL: https://safe-text.us/api/lastpull/. 
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sync-Protocol
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function lastpullAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
						
							// execute sync last pull recall
							$recordsOut = $user->getRelationship('device')->lastpull();
							// log the output
							if (sizeof($recordsOut) > 0) $this->config['log']->write('Records Out: ' . json_encode($recordsOut));

							// load output into view
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', $recordsOut);
					
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Settings Action.
	 * 
	 * User settings are stored on the server and can be get/put by the mobile app using the Settings web service
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Settings
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function settingsAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
				// Create a database connection to share
				$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
							
				// authenticate token with stored procedure
				require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
				$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
		
				if ($user instanceof SafetextUser && $user->isValid()) {
			
					if (MS_REQUEST_METHOD === 'GET') {
						/* GET SETTINGS */
						// Get settings via stored procedure
						$result = current($db->CALL("getSettings('" . $user->id . "')"));
					
						// load output into view
						$viewObject->setValue('status', 'success');
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						$viewObject->setValue('data', $result);
					
					} else if (MS_REQUEST_METHOD === 'POST') {
						/* PUT SETTINGS */
						array_key_exists('username', $this->params)? $username = $this->escapeForDb($this->params['username']): $username = '';
						array_key_exists('firstname', $this->params)? $firstname = $this->escapeForDb($this->params['firstname']): $firstname = '';
						array_key_exists('lastname', $this->params)? $lastname = $this->escapeForDb($this->params['lastname']): $lastname = '';
						array_key_exists('email', $this->params)? $email = $this->escapeForDb($this->params['email']): $email = '';
						array_key_exists('phone', $this->params)? $phone = $this->escapeForDb($this->params['phone']): $phone = '';
						array_key_exists('pass', $this->params)? $pass = $this->escapeForDb($this->params['pass']): $pass = '';
						array_key_exists('language', $this->params)? $language = $this->params['language']: $language = 'en';
						array_key_exists('notifications_on', $this->params)? $notifications_on = $this->params['notifications_on']: $notifications_on = '0';
						array_key_exists('whitelist_only', $this->params)? $whitelist_only = $this->params['whitelist_only']: $whitelist_only = '0';
						array_key_exists('enable_panic', $this->params)? $enable_panic = $this->params['enable_panic']: $enable_panic = '0';
						
						if (strlen($username) > 3 && strlen($username) < 17) {
							// Put settings via stored procedure
							$result = current($db->CALL("putSettings('" . $user->id . "','$username','$firstname','$lastname','$email','$phone','$pass','$language','$notifications_on','$whitelist_only','$enable_panic')"));
							if (!$result['msg']) {
								$viewObject->setValue('status', 'success');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
							} else {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => $result['msg']));
							}
							
						} else {
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', array('message' => 'Username must be between 3 and 16 characters in length'));
						}
					} else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
					}
				} else { // invalid token
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
				}
			} else { // no token
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => 'Missing auth token'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	
	
	/**
	 * Escape For DB.
	 * Escapes a string to be sent to a DB stored procedure.
	 *
	 * @param String $val
	 * @return String
	 */
	protected function escapeForDb($val) {
		return str_replace("'", "\'", $val);
	}
	
	
	public function testAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
		$result = $db->call("syncPull('3','8');");
		
		$test = '';
		if (is_array($result)) {
			foreach ($result as $row) {
				foreach ($row as $key=>$val) {
					$test .= "$key=$val,";
				} 
			}
		} else{
			$test = $result;
		}
		
		$viewObject->setValue('Test', $test);
		
	} 
	 
}