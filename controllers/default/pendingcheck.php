<?php

/**
 * Controller for Pending Check Webservice.
 *
 *
 * Quick endpoints to check just the count of unread messages that exist for a particular user.
 *
 */
class PendingcheckController extends MsController {
	
	/**
	 * Default Action.
	 * 
	 * Called when no action is defined.
	 *
	 * POST requests return an auth token if a matching username/password is found.
	 * GET requetss return the pending message count. 
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Pending-Check
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') { // POST method requests an auth token
				if (array_key_exists('HTTP_X_SAFETEXT_USERNAME', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_USERNAME'] !== '') {
					if (array_key_exists('HTTP_X_SAFETEXT_CODE', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_CODE'] !== '') {
																
						// Create a database connection to share
						$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
	
						// generate token via stored procedure
						$result = current($db->call("UsernameCodeToId('" . $_SERVER['HTTP_X_SAFETEXT_USERNAME'] . "','" . $_SERVER['HTTP_X_SAFETEXT_CODE'] . "')"));
						
						if ($result['id'] > 0) {
						
							$token = $result['id'] . '-' . md5('SafETexT@pendiNGCh#eckSAlt' . $result['id']);
						
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('data', array('token' => $token));
							
							// log the auth request
							$this->config['log']->write('User: ' . $result['id'] . ', Token: ' . $result['token'], 'Pending Check AUTH Request');

						} else { // unsuccessful auth token generation
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'No match'));
						}

					} else { // no password
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing SafeText password'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText username'));
				}
			} else if 	(MS_REQUEST_METHOD === 'GET') {  // GET method requests the pending messages count
				if (array_key_exists('HTTP_X_SAFETEXT_CHECKTOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_CHECKTOKEN'] !== '') {
					// validate token
					$id_hash = explode('-', $_SERVER['HTTP_X_SAFETEXT_CHECKTOKEN']);
					if ( $id_hash[1] == md5('SafETexT@pendiNGCh#eckSAlt' . $id_hash[0]) ) {
						// Create a database connection to share
						$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
						
						// get unread message count via stored procedure
						$result = current($db->call("pendingCheck('" . $id_hash[0] . "')"));
				
						if ($result) {
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('data', array('count' => $result['count']));
							
							// log the request
							$this->config['log']->write('User: ' . $id_hash[0] . ', Count: ' . $result['count'], 'Pending Check Request');
						} else { // error in stored procedure
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'There was a problem trying to pull pending messages with that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Invalid SafeText check token'));
					}
				} else { // no token
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText check token'));
				}
			} else { // bad request verb
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for auth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}

	 
}