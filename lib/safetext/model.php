<?php

/**
 * SafeText Model.
 * Updates the framework to account for the fact that all db queries are being made via stored procedures for SafeText.
 *
 */
abstract class SafetextModel extends MsModel {
	// Implementation Methods
	/**
	  * Save.
	  *
	  * Persist updated column values and relationships to database. Overridden to use stored procedure
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
	  * Persist updated column values as a new database row. Overridden to use stored procedure
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
	
	/**
	  * Purge.
	  *
	  * Deletes this instance and all related dependencies.
	  *
	  * @return bool
	  *
	  */
	 public function purge() {
		return false;
	 }
	 
	 
	 /**
	  * Relate.
	  *
	  * Sets up a relationship with another model. Overridden to simplify by merely passing a pre-loaded model
	  *
	  * @param string $label Label to associate this relationship with.
	  * @param MsModel $relatedModel
	  *
	  * @return void
	  *
	  */
	public function relate($label, &$relatedModel)
	{
		$this->relationships[$label] = array(
			'model' => $relatedModel
		);
	}
	
	
	/**
	 * Escape For DB.
	 * Escapes a string to be sent to a DB stored procedure.
	 *
	 * @param String $val
	 * @return String
	 */
	static function escapeForDb($val) {
		return str_replace("'", "\'", $val);
	}
	
		
}