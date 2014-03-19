<?php

/**
 * Front Website Controller.
 *
 *
 */
class FrontController extends MsController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will render a front website page
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
		
		// see if user is logged in
		// TODO
		
		
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
		
		// set title
		if ($page === 'index') $viewObject->setTitle($this->config['productName']);
			else $viewObject->setTitle($this->config['productName'] . ' - ' . ucfirst($page));
		
		
		
		
		// set view data
		$viewObject->setValue('page', $page);
		$viewObject->setVlalue('user', null); // TODO
		
	 }
	
	 
}