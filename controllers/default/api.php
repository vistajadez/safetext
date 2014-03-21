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
						if (array_key_exists('device_signature', $this->params) && $this->param['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->param['device_description'] !== '') {
								// Create a database connection to share
								$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
			
								// first authenticate the username/password pair via db stored procedure
								require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
								$tokenDetails = SafetextUser::generateToken($_SERVER['HTTP_X_SAFETEXT_USERNAME'], $_SERVER['HTTP_X_SAFETEXT_PASSWORD'], $this->params['device_signature'], $this->params['device_description'], $db, $this->config);
								
								if ($tokenDetails['id'] > 0) {
								
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('data', array('token' => $tokenDetails['token'], 'user' => $tokenDetails['id']));
			
		
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
		
		
		
 
	 
	 
}