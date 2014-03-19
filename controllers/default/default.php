<?php

/**
 * Default Controller.
 *
 *
 */
class DefaultController extends MsController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will forward to the web client page if logged in, otherwise to the login page.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			header("Location: $redirect");
				
			return; // terminate controller action
		}
		
		// TODO: Test if user is logged in. If so, forward to dashboard. Otherwise, forward to login. For now shunt everything to login
		$this->forward($viewObject, 'login', 'auth');
	 }
	
	 
}