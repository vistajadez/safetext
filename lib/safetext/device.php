<?php

/**
 * SafeText Mobile Device Object.
 * Represents an application user's mobile device.
 *
 */
class SafetextDevice extends MsModel {
	// Database
	public $dbTable = 'sync_device'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('id');
	
	// Columns
	public $columns = 'id,user_id,signature,description,is_initialized,token'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods

	
	
	
	
	// Methods unique to Device Object
	
	
	/**
	  * Purge.
	  *
	  * Deletes this instance and all related dependencies.
	  *
	  * @return bool
	  *
	  */
	 public function purge() {
		
	 }
	
	/**
	  * Save.
	  *
	  * Persist updated column values and relationships to database. Overridden to use prepared statement
	  * @return void
	  *
	  */
	public function save()
	{
		if (($this->isValid()) && (sizeof($this->changedColumns) > 0)) {
			// save ONLY changed columns
			$values = array();
			foreach ($this->changedColumns as $col) $values[$col] = $this->$col;
//			$this->db->insert($this->dbTable, $this->columnValues, true);
			
			// reset change tracker
			$this->unchanged();
/*			
		$trace = debug_backtrace();
				trigger_error(
					'Saving to ' . $this->dbTable . '...',
					E_USER_NOTICE);
*/
		}
	}
	
	
	/**
	  * Save New.
	  *
	  * Persist updated column values as a new database row. Overridden to use prepared statement
	  *
	  * @param bool $allowOverwrite (Optional) True to use "REPLACE INTO" instead of "INSERT INTO" to overwrite any existing model. Default is false.
	  *
	  * @return int Id of new row if it has an autoincrement column. Returns 0 otherwise.
	  *
	  */
	public function saveNew($allowOverwrite = false)
	{
		if (($this->isValid()) && (sizeof($this->changedColumns) > 0)) {
			
			// save to db
//			$newId = $this->db->insert($this->dbTable, $this->columnValues, $allowOverwrite);
			
			// reset change tracker
			$this->unchanged();
			
			return $newId;
		}
		return false;
	}
	
	
	
	
	
	// Class Methods



	
}