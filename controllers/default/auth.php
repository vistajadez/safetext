<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Auth Controller.
 *
 *
 */
class AuthController extends SafetextClientController {
	/**
	 * Login Action.
	 * 
	 * Display a login form.
	 * 
	 * @param MsView $viewobject
	 * @return void
	 */
	 public function loginAction(&$viewObject) {
	 	// are we already logged in? If so, go to dashboard
		if ($this->init($viewObject, $this->config['productName'] . ' - ' . $viewObject->t('Login'))) {
			$this->forward($viewObject, 'home', 'webclient');
			return;
		}
		
		// validate input and persist the login form fields
		array_key_exists('redirect', $this->params) ? $redirect = $this->params['redirect'] : $redirect = 'home';
		array_key_exists('username', $this->params) ? $username = trim(strtolower($this->params['username'])) : $username = '';
		array_key_exists('pass', $this->params) ? $pass = trim($this->params['pass']) : $pass = '';
		
		$viewObject->setValue('redirect', $redirect);
		$viewObject->setValue('username', $username);
	 }
	 
	/**
	 * Logout Action.
	 * 
	 * Clear auth cookie and display login form.
	 * 
	 * @param MsView $viewobject
	 * @return void
	 */
	 public function logoutAction(&$viewObject) {
	 	// clear cookie
	 	setcookie("token", "", time() - 3600, '/');
	 	//unset($_COOKIE['token']);
	 
	 	// page title
	 	$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Login'));
	 
	 	// reset view script to login view script
	 	$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . 'auth' . DS . 'login.phtml');
	 }
	
	 
	/**
	 * Register Action.
	 * 
	 * Display a registration form.
	 * 
	 * @param MsView $viewobject
	 * @return void
	 */
	 public function registerAction(&$viewObject) {
	 	// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			header("Location: $redirect");
				
			return; // terminate controller action
		}
		
		// Page title
		$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Register'));

	 }


	 
	 
	 
}