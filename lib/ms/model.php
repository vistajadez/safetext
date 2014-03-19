<?php

/**
 * Mediasoft Model Object.
 *
 *
 */
abstract class MsModel {
	protected $config;  // pointer to bootstrap config array
	protected $db;	// database connection (MsDb)
	
	// Columns
	public $columns = '*';
	protected $columnValues;  // associative array where keys = column names, vals = column values
	protected $changedColumns = array(); // array of any columns that have been changed
	
	// Related Models
	protected $relationships = array();

	
	/**
	 * Constructor.
	 * 
	 * @param mixed[] $config_in Configuration values from bootstrapper.
	 * @param MsDb $db_in (Optional) MsDb shared database connection to use with this model. If one isn't passed, a new one will be created.
	 * @param mixed[]|string $columns_in (Optional) String or array of column names to use for this instance i.e.: 'id,first,last' or: array('id', 'first', 'last'), or: '*'. Default is '*' unless set by child class.
	 * @param MsModel $associator_in (Optional) If this model is being created within another model as as an associated relationship, the creating model should pass itself as a pointer here. Default is NULL.
	 *
	 * @return void
	 */
	function __construct(&$config_in, $db_in=NULL, $columns_in=NULL, &$associator_in=NULL) {
		$this->config = $config_in;
		
		// create a new database connection if a shared one hasn't been passed
		if (!$db_in instanceof MsDb) $this->db = new MsDb($config_in['dbHost'], $config_in['dbUser'], $config_in['dbPass'], $config_in['dbName']);
			else $this->db = $db_in;
		
		// set columns to use for this instance of the object
		if ($columns_in) $this->columns = $columns_in;
		
		// associate related models, store instances in $this->relationships array
		$this->initRelationships($associator_in);
	}
	
	
	/**
	 * Destructor.
	 *
	 * @return void
	 */
	function __destruct() {
	  // synch to database on close. Only is performed if changes have been made to model
      $this->save();
	}
	
	
	/**
	 * Get Config Pointer.
	 *
	 * @return mixed[]
	 */
	function getConfig() {
	  return $this->config;
	}
	
	
	/**
	 * Get Database Connection Pointer.
	 *
	 * @return MsDb
	 */
	function getDb() {
	  return $this->db;
	}
	
	
	/**
	 * To Array.
	 *
	 * @return mixed[]
	 */
	function toArray() {
	  if (is_array($this->columnValues)) return $this->columnValues;
	  	else return array();
	}
	
	
	
	/**
	 * Identify.
	 * 
	 * Identifies a unique rowset in the database based on an identifying rule, which can be unique to each model class by overriding this method. Can be a string or array of parameters.
	 * By default, accepts $where parameter as described below.
	 * Implementation of this method should set the model's columnValues variable with a valid key-value array of the row's columns, or do nothing if no matching row is found.
	 *
	 * @param mixed[] $where Array of conditionals as an array (i.e. 'name', '=', 'john doe').
	 * @param bool $preload_relationships (Optional) True=preload related models, false=don't preload and instead only load related models from DB on demand. Default is true.
	 *
	 * @return void
	 *
	 */
	 public function identify($where, $preload_relationships=true) {
		if ($this->db->isConnected()) {
			if ((sizeof($this->relationships) < 1) || (!$preload_relationships)) {
				// no relationships
				$query = $this->db->formatSelectQuery($this->columns, $this->dbTable, $where);
			} else {
				// if relationships exist, join tables for an efficient single-db query of all related models
				$table = '';
				$inner_join = '';
				$left_join = array();
				$joinwhere = '';
				foreach ($where as $this_column => $where_array) {
					if (array_key_exists('conj', $where_array)) $conj = $where_array['conj']; else $conj = 'AND';
					$joinwhere .= ' ' . $conj . ' ' . $this->dbTable . '.' . $this_column . $where_array['operator'] . '\'' . $where_array['value'] . '\'';
				}
				if (is_array($this->columns)) $columns = $this->columns;
					else $columns = explode(',', $this->columns);
				foreach ($columns as &$this_column) $this_column = $this->dbTable . '.' . $this_column;
				foreach ($this->relationships as $this_label => $relationship_array) {
					$relationship_where = '';
					foreach ($relationship_array['column_map'] as $local_column => $foreign_column) {
						if ($relationship_where != '') $relationship_where .= ' AND';
						$relationship_where .= ' ' . $this->dbTable . '.' . $local_column . '=' . $relationship_array['model']->dbTable . '.' . $foreign_column;
					}
					
					if (is_array($relationship_array['model']->columns)) $model_columns = $relationship_array['model']->columns;
						else $model_columns = explode(',', $relationship_array['model']->columns);
					foreach ($model_columns as $this_model_column) $columns[] = $relationship_array['model']->dbTable . '.' . $this_model_column . ' AS \'' . $this_label . '.' . $this_model_column . '\'';	
					
					if ($table == '') {
						$table = $relationship_array['model']->dbTable;
						$inner_join[] = $this->dbTable . ' ON ' . $relationship_where . $joinwhere;
					} else {
						$left_join[] = $relationship_array['model']->dbTable . ' ON ' . $relationship_where . $joinwhere;
					}
				}
				
				$query = $this->db->formatSelectQuery($columns, $table, NULL, NULL, $inner_join, $left_join);
			}
			
			/*
				$trace = debug_backtrace();
				trigger_error(
					'query: ' . $query,
					E_USER_NOTICE);
			*/
	
			$result = $this->db->query($query);
			if ($result->isValid()) {
				if ((sizeof($this->relationships) < 1) || (!$preload_relationships)) {
					// no relationships
					$this->columnValues = $result->fetch_assoc();
				} else {
					// if relationships exist, seperate result into various related models
					$this->columnValues = array();
					foreach ($result->fetch_assoc() as $column => $val) {
						
						if (strpos($column, '.') !== false) { // a dot in the column value may indicate a related object's column
							$label_column = explode('.', $column);
							if (is_object($this->relationships[$label_column[0]]['model'])) {
								$this->relationships[$label_column[0]]['model']->columnValues[$label_column[1]] = $val;
								
							} else {
								$this->columnValues[$column] = $val;
							}
						} else {
							$this->columnValues[$column] = $val;
						}
					}
				}
			}
		}
	 }
	 
	 	 
	 /**
	  * Is Valid.
	  *
	  * Tests whether or not this is a valid object identified with a row in the database (i.e. column values have been set).
	  *
	  * @return bool
	  *
	  */
	  public function isValid() {
		  return is_array($this->columnValues);
	  }
	  

	/**
	  * Save.
	  *
	  * Persist updated column values and relationships to database.
	  *
	  * @return void
	  *
	  */
	public function save()
	{
		if (($this->isValid()) && (sizeof($this->changedColumns) > 0)) {
			// save ONLY changed columns
			$values = array();
			foreach ($this->changedColumns as $col) $values[$col] = $this->$col;
			$this->db->insert($this->dbTable, $this->columnValues, true);
			
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
	  * Persist updated column values as a new database row.
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
			$newId = $this->db->insert($this->dbTable, $this->columnValues, $allowOverwrite);
			
			// reset change tracker
			$this->unchanged();
			
			return $newId;
		}
		return false;
	}
	
	
	/**
	  * Unchanged.
	  *
	  * Reset change tracker. Puts model in a state where it assumes to be already synched with database. 
	  * Subsequent save() calls will not initiate a db push unless a column is updated first.
	  *
	  * @return void
	  *
	  */
	public function unchanged()
	{
		// reset change tracker
		$this->changedColumns = NULL;
		$this->changedColumns = array();
	}


	/**
	  * Initialize Relationships
	  *
	  * To be overridden. Defines related models to always be associated with this one.
	  *
	  * @param MsModel $associator
	  *
	  */
	public function initRelationships(&$associator)
	{
	}
	
	
	/**
	  * Add Relationship.
	  *
	  * Add a relationship to a model during runtime (as opposed to being defined in model class and always present).
	  *
	  * @param string $label Label to associate this relationship with.
	  * @param string $model_class Class name of the related model (i.e. WedshareColors).
	  * @param string[] $column_map
	  * @param $columns_in string|mixed[] (Optional) Comma-delimited string or array of columns to load in the related model. Overrides the columns defined in the model class..
	  * @param bool $overwrite (Optional) If true, any existing relationship of the same label will be replaced. Default is false.
	  *
	  * @return MsModel The related model, uninitialized.
	  */
	public function addRelationship($label, $model_class, $column_map, $columns_in=NULL, $overwrite=false)
	{
		// make sure class file has been included before we instantiate
		$classNameArray = MsUtils::camelcaseToArray($model_class);
		$path = MS_PATH_BASE . DS . 'lib' . DS . strtolower($classNameArray[0]) . DS . strtolower($classNameArray[1]) . '.php';
		if (file_exists($path)) require_once($path);
		
		 eval('$model = new ' . $model_class . '($this->config, $this->db, $columns_in, $this);');
		 if (is_object($model)) $this->relate($label, $model, $column_map, $overwrite);
		 
		 return $model;
	}
	
	
	/**
	  * Relate.
	  *
	  * Sets up a relationship with another model.
	  *
	  * @param string $label Label to associate this relationship with.
	  * @param MsModel $relatedModel
	  * @param string[] $column_map
	  * @param bool $overwrite (Optional) If true, any existing relationship of the same label will be replaced. Default is false.
	  *
	  * @return void
	  *
	  */
	protected function relate($label, &$relatedModel, $column_map, $overwrite=false)
	{
		if (array_key_exists($label, $this->relationships)) {
			if (!$overwrite) return; // don't create a relationship with a model if another model is already set up for that relationship
			// unless overwrite is set to true
			$this->relationships[$label]['model'] = NULL;
			unset($this->relationships[$label]);
		}
		
		$this->relationships[$label] = array(
			'model' => $relatedModel,
			'column_map' => $column_map
		);
	}
	
	/**
	  * Get Relationship.
	  *
	  * Given a label, returns the associated relationship model. If no relationship model exists for that label, returns NULL.
	  *
	  * @param string $label
	  * 
	  * @return MsModel|null
	  *
	  */
	public function getRelationship($label)
	{
		if ($this->isValid()) {
			if (is_object($this->relationships[$label]['model'])) { 
				if ($this->relationships[$label]['model']->isValid()) { // related model is preloaded
					return $this->relationships[$label]['model'];
				} else {
					$relationship_where = array();
					foreach ($this->relationships[$label]['column_map'] as $local_column => $foreign_column)
						if (array_key_exists($local_column, $this->columnValues)) $relationship_where[$foreign_column] = array('value' => $this->columnValues[$local_column], 'operator' => '=');
						else $relationship_where[$foreign_column] = array('value' => $local_column, 'operator' => '='); // if not in columns, just use the passed column name as the value itself
						
	
					// load related model
					$this->relationships[$label]['model']->identify($relationship_where);
					// return related model if it properly loaded
					if ($this->relationships[$label]['model']->isValid()) return $this->relationships[$label]['model'];
				}
			}
		}
		return NULL;
	}
		
	
	
	/**
	  * Set Value - Data Setter.
	  *
	  * Sets a value of the columnValues array.
	  *
	  * @param string $name
	  * @param mixed $value
	  *
	  * @return void
	  *
	  */
	public function setValue($name, $value)
	{
		if (!is_array($this->columnValues)) $this->columnValues = array();
		$this->columnValues[$name] = $value;
		$this->changedColumns[] = $name; // track this change for later DB synch
	}
	
	
	/**
	  * Get Value - Data Getter.
	  *
	  * Gets a value of the columnValues array.
	  *
	  * @param string $name
	  *
	  * @return mixed
	  *
	  */
	public function getValue($name)
	{
		if ($this->isValid()) {
			if (array_key_exists($name, $this->columnValues)) {
				return $this->columnValues[$name];
			}
		}
		
		return NULL;
	}
	
	
	/**
	  * Set ColumnValues - Set all column values.
	  *
	  * Sets this model's column values to the passed array. Does NOT trigger a changed flag.
	  *
	  * @param mixed[] $values values
	  *
	  * @return void
	  *
	  */
	public function setColumnValues(&$values)
	{
		$this->columnValues = $values;
	}
	
	
	/**
	  * Delete.
	  *
	  * Should be overridden in child classes for whenever this model is called to be deleted. Recommended to 
	  * set a "deleted" flag for the model and all dependencies, for example, rather than physically removing them
	  * from the database.
	  *
	  * @return void
	  *
	  */
	public function delete()
	{
		// Add your code to flag for delete here. For example:
		// $this->setValue('is_deleted', '1');
		// $this->setValue('date_deleted', date('Y-m-d'));
	}
	
	
	/**
	  * Purge.
	  *
	  * Deletes this model's database entry.
	  *
	  * @return bool
	  *
	  */
	public function purge()
	{
		if ($this->isValid()) {
			if (!is_array($this->pk)) return false; // if a primary key isn't specified, we can't delete
			
			// build where clause to delete based on primary key for this model
			$where = array();
			foreach ($this->pk as $keyName) {
				if ($this->getValue($keyName) == NULL) return false; // if a primary key isn't set, we can't delete
				$where[$keyName] = array('value' => $this->getValue($keyName), 'operator' => '=');
			}	
			
			$query = $this->db->formatDeleteQuery($this->dbTable, $where);
			$result = $this->db->query($query);
	
			// clear any update flags, as we won't need to update a deleted model
			$this->changedColumns = array();
			
			return true;
		}
	}
	
	  
	/**
	  * Set - Data Setter.
	  *
	  * Called whenever an undelcared class variable is asked to be set. Will set in the columnValues array.
	  *
	  * @param string $name
	  * @param mixed $value
	  *
	  * @return void
	  */
	public function __set($name, $value)
	{
		$this->setValue($name, $value);
	}

	/**
	  * Get - Data Accessor.
	  *
	  * Called whenever an undeclared class variable is requested. Will look in the columnValues array.
	  *
	  * @param string $name
	  *
	  * @return mixed
	  *
	  */
	public function __get($name)
	{
		return $this->getValue($name);
	}
	
	
	
}