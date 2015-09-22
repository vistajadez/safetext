<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Admin Controller.
 *
 *
 */
class AdminController extends SafetextClientController {

	/**
	 * Login Action.
	 * 
	 * Display a Login form.
	 * 
	 * @param MsView $viewobject
	 * @return void
	 */
	 public function loginAction(&$viewObject) {
	 	// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			header("Location: $redirect");
				
			return; // terminate controller action
		}
		
		// Page title
		$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Administration'));

	 }
	 
	 /* Logou Action
	 */
	 
	 public function logoutAction(&$viewObject) {
	 	// clear cookie
	 	setcookie("admintoken", "", time() - 3600, '/');
	 
	 	// page title
	 	$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Login'));
	 
	 	// reset view script to login view script
	 	$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . 'admin' . DS . 'login.phtml');
	 }
	 
	 /**
	  * Auth Action
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
							
								$result = current($db->CALL("adminLogin('" . $_SERVER['HTTP_X_SAFETEXT_USERNAME'] . "','" . md5($_SERVER['HTTP_X_SAFETEXT_PASSWORD']) . "')"));
								
								if($result['id']>0) {
									$viewObject->setValue('status', 'success');
									$viewObject->setValue('data', array('admintoken' => $result['admintoken'], 'message' => $result['msg']));
								}
								else {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => $result['msg']));
								}
									
							
							} else { // no device description
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
	 * Contacts Action.
	 * 
	 * Render web client dashboard contacts view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function usersAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!isset($_COOKIE['admintoken'])) {
			$this->forward($viewObject, 'login', 'admin');
		} else {
			$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
			$users = $db->CALL("getuserList()");
			
			$viewObject->setValue('contacts', $users);
		}
	 }



	/* Change Account Username & Password */
	 
	 public function accountAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!isset($_COOKIE['admintoken'])) {
			$this->forward($viewObject, 'login', 'admin');
		} else {
		// Page title
		$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Change Password'));
			
		}
	} 
	
	 public function accountpassAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
						if (array_key_exists('device_signature', $this->params) && $this->params['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->params['device_description'] !== '') {
							
								array_key_exists('pass', $this->params) ? $pass = $this->params['pass']: $pass = '';
								// Create a database connection to share
								$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
								$result = current($db->CALL("adminUpdateAccount('" . md5($pass) . "')"));
								
								$viewObject->setValue('status', 'success');
									
							} else { // no device description
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Missing device description'));
							}
						} else { // no device sig
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Missing device signature'));
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
	 * Change Password Action.
	 * 
	 * Renders edit contact view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!isset($_COOKIE['admintoken'])) {
			$this->forward($viewObject, 'login', 'admin');
		} else {
			array_key_exists('id', $this->params) ? $contact = $this->params['id']: $contact = '';
			
			$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
			$users = $db->CALL("getuserDetails('".$contact."')");
			
			$viewObject->setValue('contact', $users);
			
		}
	 }
	 
	 
	 
	 public function changepassAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
						if (array_key_exists('device_signature', $this->params) && $this->params['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->params['device_description'] !== '') {
								
								array_key_exists('contact', $this->params) ? $contact = $this->params['contact']: $contact = '';
								array_key_exists('pass', $this->params) ? $pass = $this->params['pass']: $pass = '';
								// Create a database connection to share
								$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
								$result = current($db->CALL("adminUpdatePass('" . $contact . "','" . md5($pass) . "')"));
								
								$viewObject->setValue('status', 'success');
									
							} else { // no device description
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Missing device description'));
							}
						} else { // no device sig
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Missing device signature'));
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