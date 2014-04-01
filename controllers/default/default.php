<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Default Controller.
 *
 *
 */
class DefaultController extends SafetextClientController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will forward to the web client page if logged in, otherwise to the login page.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if ($this->init($viewObject)) $this->forward($viewObject, 'home', 'webclient');
			else $this->forward($viewObject, 'login', 'auth');
	 }
	
	 
}