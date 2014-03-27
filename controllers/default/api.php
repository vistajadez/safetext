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