<?php

/**
 * Mediasoft View Helper
 *
 * Copyright (c) 2013 Mediasoft Technologies, Inc.
 * Author: Jason Melendez
 *
 */
class MsHelper {
	protected $view; // pointer to the view object which called this helper
	
	
	/**
	 * Constructor.
	 * 
	 * @param MsView $viewObject The view which is calling this helper
	 *
	 * @return void
	 */
	function __construct(&$viewObject) {
		$this->view = $viewObject;
	}
	
	
	
	/**
	 * Get Calling View Object.
	 *
	 * @return MsView
	 */
	function getView() {
	  return $this->view;
	}
	
}