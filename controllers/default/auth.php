<?php

/**
 * Auth Controller.
 *
 *
 */
class AuthController extends MsController {
	/**
	 * Login Action.
	 * 
	 * Process a login form. Task is to identify the viewer as a user profile.
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
		
		// validate input and persist the login form fields
		array_key_exists('redirect', $this->params) ? $redirect = $this->params['redirect'] : $redirect = 'home';
		array_key_exists('username', $this->params) ? $username = trim(strtolower($this->params['username'])) : $username = '';
		array_key_exists('pass', $this->params) ? $pass = trim($this->params['pass']) : $pass = '';
		
		$viewObject->setValue('redirect', $redirect);
		$viewObject->setValue('username', $username);
		
		// Page title
		$viewObject->setTitle($this->config['productName'] . ' - ' . $viewObject->t('Login'));
			
		if ($login == '') {
			$viewObject->setValue('feedback', $viewObject->t('Please enter your email address to login'));
		} else if ($pass == '') {
			$viewObject->setValue('feedback', $viewObject->t('Please enter your password to login'));
		} else {
			
			
			
			
			
			
		}

	 }
	
	 
	/**
	 * Register Action.
	 * 
	 * Registration form.
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