<?php

/**
 * Fallback Controller.
 *
 *
 */
class NotfoundController extends MsController {
	
	
	/**
	 * Not Found Controller Action.
	 * 
	 * Called when a view is requested which has not been defined.
	 *
	 * @param MsView $viewObject
	 * @return void
	 *
	 */
	 public function nocontrollerAction($viewObject) {
		 // catchall: try to render the front-facing website page whose name (url) is being passed in the controller slot'
		 if (file_exists(MS_PATH_BASE . DS . 'views' . DS . MS_MODULE . DS . 'scripts' . DS . 'front' . DS . MS_CONTROLLER . '.phtml')) {
			 $this->params['page'] = MS_CONTROLLER; // MS_CONTROLLER contains name of the original controller that was called
			 $this->forward($viewObject, 'default', 'front');
		 } else {
			 $viewObject->setTitle('404: Doh!');
		 }
	 }
	 
	/**
	 * Not Found Action Action.
	 * 
	 * Called when a view is requested which has not been defined.
	 * 
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function noactionAction($viewObject) {
		  $viewObject->setTitle('404: Doh!');
	 }
	 
}