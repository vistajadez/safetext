<?php

/**
 * SafeText Model.
 * Updates the framework to account for the fact that all db queries are being made via stored procedures for SafeText.
 *
 */
class SafetextModelCollection extends MsModelCollection {
	// Implementation Methods
	
	
	/**
	 * Load.
	 * 
	 * Loads this collection with model data stored as an array, normally obtained from a stored procedure call.
	 * 
	 * @parameter 	mixed[] 		$modelArrayIn Associative array of model data, where each row is a model to load.
	 * @return void
	 *
	 */
	 public function load($modelArrayIn) {
	 	// make sure class file has been included before we instantiate
		$classNameArray = MsUtils::camelcaseToArray($this->classname);
		$path = MS_PATH_BASE . DS . 'lib' . DS . strtolower($classNameArray[0]) . DS . strtolower($classNameArray[1]) . '.php';
		if (file_exists($path)) require_once($path);
		
		// load each model into the collection
	 	foreach ($modelArrayIn as $this_model) {
		 	eval('$model = new ' . $this->classname . '($this->config, $this->db);');
		 	if (is_object($model)) {
		 		$model->setColumnValues($this_model);
				$this->models[] = $model;
		 	}
	 	}
	 	
	 	// clear internal search index since we've loaded new data
		if (sizeof($this->findMap) > 0) {
			unset($this->findMap);
			$this->findMap = array();
		}
	 }
	 
	 
	 
	 
}	