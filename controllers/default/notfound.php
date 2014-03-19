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
		  $viewObject->setTitle('404: Doh!');
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