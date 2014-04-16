<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Web Client Controller.
 *
 *
 */
class WebclientController extends SafetextClientController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will forward to home if logged in, otherwise to the login page.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if ($this->init($viewObject)) $this->forward($viewObject, 'home');
			else $this->forward($viewObject, 'login', 'auth');
	 }
	
	 
	 
	/**
	 * Home Action.
	 * 
	 * Render web client dashboard home.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function homeAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load all contacts and messages for current user
			$this->_loadFolders($viewObject);
		
		
		
			// TODO
		
		
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
	 public function contactsAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load all contacts and messages for current user
			$this->_loadFolders($viewObject);
			
		}
	 }
	 
	 
	/**
	 * Messages Action.
	 * 
	 * Render web client dashboard messages view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function messagesAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load all contacts and messages for current user
			$this->_loadFolders($viewObject);
			
			// are we viewing a specific folder?
			array_key_exists('folder', $this->params) ? $folder = $this->params['folder']: $folder = 'inbox';
			
			$viewObject->setValue('folder', $folder);
			
		}
	 }
}