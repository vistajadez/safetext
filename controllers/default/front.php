<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Front Website Controller.
 *
 *
 */
class FrontController extends SafetextClientController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will render a front website page
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// ensure we're using https and see if user is logged in
		$this->init($viewObject);
		
		
		// determine which view script to render
		array_key_exists('page', $this->params) ? $page = $this->params['page'] : $page = '';
		if ($page === '') { // default if logged in is dashboard, otherwise login page
			
			// TODO
			
			
		}
		if ($page != 'index' && !file_exists(MS_PATH_BASE . DS . 'views' . DS . MS_MODULE . DS . 'scripts' . DS . 'front' . DS . $page . '.phtml')) {
			$this->forward($viewObject, 'nocontroller', 'notfound');
			return;
		}
		
		$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . 'front' . DS . $page . '.phtml');
		
		
		// set view data
		$viewObject->setValue('page', $page);
		$viewObject->setVlalue('user', null); // TODO
		
	 }
	
	 
}