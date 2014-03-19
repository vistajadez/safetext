<?php

/**
 * Front Website Controller.
 *
 *
 */
class TestController extends MsController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		$viewObject->setResponseType('json');
		$viewObject->setValue('status', 'success');
		$viewObject->setValue('data', array('message' => 'Looks OK'));
	 }
	
	 
}