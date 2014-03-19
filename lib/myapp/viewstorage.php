<?php

/**
 * MyApp View Storage.
 *
 * An example class representing the collection of persisted stored key-value entries for a specific viewer. Viewer data is stored
 * in a database on the server side rather than in cookies, which has several key advantages.
 * This example also illustrates how to extend the ModelCollection class.
 *
 */
class MyappViewstorage extends MsModelcollection {
	
	// viewer associated with this persisted storage
	protected $viewer;
	
	
	// Implementation Methods
	
	
	/**
	 * Constructor.
	 * 
	 * Override parent constructor.
	 *
	 * @param MyappViewer $viewer_in Viewer model to associate the storage with. This is a hypothetical object but could be simply an ID.
	 *
	 * @return void
	 *
	 */
	function __construct(&$viewer_in) {
		$this->viewer = $viewer_in;
		
		parent::__construct('MyappViewstorageitem', $viewer_in->getConfig(), $viewer_in->getDb());
	}
	
	
	
	// Methods Unique to View Storage
	
	/**
	 * Get Value.
	 * 
	 * Given a key, loads the persisted value for that key associated with this container's viewer.
	 *
	 * @param string $key
	 *
	 * @return string|null Mapped value, or null will be returned if there is a problem with the associate viewer object.
	 *
	 */
	function getValue($key) {
		// see if we've already loaded that value
		if ($this->find('keyname', $key, true) != NULL) return $this->find('keyname', $key, true)->value;
		
		// otherwise try to get value from db
		if (!$this->viewer instanceof MyappViewer) return NULL;
		
		$this->load(
			array(
				'viewer_id' => array(
					'value' => $this->viewer->id, 'operator' => '='
				),
				'keyname' => array(
					'value' => $key, 'operator' => '='
				)
			)
		);
		
		// search by key for appropriate value
		if ($this->find('keyname', $key, true) != NULL) return $this->find('keyname', $key, true)->value;
			else return '';
	}
	
	
	/**
	 * Set Value.
	 * 
	 * Given a key and value, persists for this viewer.
	 *
	 * @param string $key
	 * @param string $value
	 *
	 * @return void
	 *
	 */
	function setValue($key, $value) {
		// see if we've already loaded this entry
		if ($this->find('keyname', $key, true) != NULL) {
			$model = $this->find('keyname', $key, true);
			$model->value = $value;
		} else {
			// add value
			if (!$this->viewer instanceof MyappViewer) return NULL;

			$model = new MyappViewstorageitem($this->config, $this->db, 'viewer_id,keyname,value');
			$model->viewer_id = $this->viewer->id;
			$model->keyname = $key;
			$model->value = $value;
		
			$model->saveNew(true/*overwrite any existing value for this key*/);
		}
	}
	
	
}




/**
 * MyApp View Storage Item.
 *
 * A single persisted entry for a viewer in their view storage. Note: these tables/columns need to exist in your database
 *
 */
class MyAppViewstorageitem extends MsModel {
	
	// Database
	public $dbTable = 'viewstorage'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('keyname');
	
	// Columns
	public $columns = 'keyname,value'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
}