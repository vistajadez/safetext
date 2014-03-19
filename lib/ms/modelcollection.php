<?php

/**
 * Mediasoft Model Collection Object.
 *
 *
 */
class MsModelCollection implements Iterator, Countable {
	protected $config;
	protected $db;	// database connection (MsDb)
	protected $classname; // name of the class of models contained in this collection. i.e. WedshareCustomer
	
	protected $models = array();	// array of model objects in this collection
	protected $findMap = array();	// if we do column find searches, an index will be stored here for efficiency should a subsequent search on the same column be made
	
	
	/**
	 * Constructor.
	 * 
	 * @param string $classname_in Name of the class of models contained in this collection. i.e. WedshareCustomer.
	 * @param mixed[] $config_in Configuration values from bootstrapper.
	 * @param MsDb $db_in (Optional) MsDb shared database connection to use with this model. If one isn't passed, a new one will be created.
	 */
	function __construct($classname_in, &$config_in, &$db_in=NULL) {
		$this->config = $config_in;
		
		// create a new database connection if a shared one hasn't been passed
		if (!$db_in) $this->db = new MsDb($config_in['dbHost'], $config_in['dbUser'], $config_in['dbPass'], $config_in['dbName']);
			else $this->db = $db_in;
			
		// store classname identifying this type of collection and the models it contains
		$this->classname = $classname_in;
	}
	
	
	/**
	 * Load.
	 * 
	 * Loads this collection with models based on criteria. Optionally sorted by orderby criteria(ion).
	 * 
	 * @parameter 	mixed[] 		$where (Optional) associative array of where criteria i.e.: array('id' => array('123', '=')) or: array('name' => array('%john%', 'like')) or: array('id' => array('50', '<')).
	 * @parameter 	string[] 		$orderby (Optional) one or more columns to sort on. i.e.: array('last', 'first') or: array('id').
	 * @parameter 	int|string		$limit (Optional) limit the returned rows by a number or span (i.e. 5 or 2,5). In a span, the first number is the starting row, the second is the numbver of rows to return.
	 * @return void
	 *
	 */
	 public function load($where, $orderby=NULL, $limit=NULL) {
		// make sure class file has been included before we instantiate
		$classNameArray = MsUtils::camelcaseToArray($this->classname);
		$path = MS_PATH_BASE . DS . 'lib' . DS . strtolower($classNameArray[0]) . DS . strtolower($classNameArray[1]) . '.php';
		if (file_exists($path)) require_once($path);
		 
		 eval('$model = new ' . $this->classname . '($this->config, $this->db);');
		 if (is_object($model)) {
			// perform query and load results as model instances
			$query = $this->db->formatSelectQuery($model->columns, $model->dbTable, $where, $orderby, NULL, NULL, $limit);
		 	if ($result = $this->db->query($query)) {
		 		foreach ($result as $this_row) {
		 			$model->setColumnValues($this_row);
					$this->models[] = $model;
					eval('$model = new ' . $this->classname . '($this->config, $this->db);');
				}
				
				// clear internal search index since we've loaded new data
				if (sizeof($this->findMap) > 0) {
					unset($this->findMap);
					$this->findMap = array();
				}
			}
		 }
	 }
	 
	 
	/**
	 * Load From Link Table.
	 * 
	 * Loads this collection with models based on references in a link table.
	 * 
	 * @parameter string $linkTableName
	 * @parameter string[] $keys Maps columns from the linktable to the model table. Keys are linktable columns, values are model table columns. i.e. array('model_id' => 'id').
	 * @parameter string[] $linkTableConditions (Optional) Array of linktable columns that must be a particular value. i.e. array('folder_id' => '4').
	 * @parameter string[] $modelTableConditions (Optional) Array of model table columns that must be a particular value. i.e. array('owner_id' => '1234').
	 * @parameter string[] $orderby (Optional) one or more columns to sort on. i.e.: array('last', 'first') or: array('id'). The table can be specified as
	 *								either "modeltable" or "linktable". i.e.: array('modeltable.first_name'). By default, the linktable is assumed.
	 * @parameter 	int|string		$limit (Optional) limit the returned rows by a number or span (i.e. 5 or 2,5). In a span, the first number is the starting row, the second is the numbver of rows to return.
	 *
	 * @return void
	 *
	 */
	 public function loadFromLinkTable($linkTableName, $keys, $linkTableConditions='', $modelTableConditions='', $orderby=NULL, $limit=NULL) {
		 // make sure class file has been included before we instantiate
		$classNameArray = MsUtils::camelcaseToArray($this->classname);
		$path = MS_PATH_BASE . DS . 'lib' . DS . strtolower($classNameArray[0]) . DS . strtolower($classNameArray[1]) . '.php';
		if (file_exists($path)) require_once($path);
		 
		 eval('$model = new ' . $this->classname . '($this->config, $this->db);');
		 if (is_object($model)) {
			// tables
			$tables = array('modeltable' => $model->dbTable, 'linktable' => $linkTableName);
			 
		 	// add table specifier to columns
			$columns = '';
			if (is_array($model->columns)) $columns_array = $model->columns;
				else $columns_array = explode(',', $model->columns);
		 	foreach ($columns_array as $this_column) {
				if ($this_column != '') {
					if ($columns != '') $columns .= ',';
					$columns .= 'modeltable.' . trim($this_column);
				}
			}
			
			// determine 'where' conditions
			$where = array();
			foreach ($keys as $linktable_column => $modeltable_column) {
				$where['modeltable.' . $modeltable_column] = array('column' => 'linktable.' . $linktable_column, 'operator' => '=');
			}
			
			if (is_array($linkTableConditions)) {
			foreach ($linkTableConditions as $linktable_column => $value)
				if (is_array($value)) $where['linktable.' . $linktable_column] = array('value' => $value['value'], 'operator' => $value['operator']);
					else $where['linktable.' . $linktable_column] = array('value' => $value, 'operator' => '=');
			}
			
			if (is_array($modelTableConditions)) {
			foreach ($modelTableConditions as $modeltable_column => $value)
				if (is_array($value)) $where['modeltable.' . $modeltable_column] = array('value' => $value['value'], 'operator' => $value['operator']);
					else $where['modeltable.' . $modeltable_column] = array('value' => $value, 'operator' => '='); 
			}
			
			// assign orderby column to linktable
			if (is_array($orderby)) {
				foreach ($orderby as &$value) {
   					if (strpos($value, '.') === false)
						$value = 'linktable.' . $value;
				}
			}
		 
		 	// perform query and load results as model instances
		 	$query = $this->db->formatSelectQuery($columns, $tables, $where, $orderby, NULL, NULL, $limit);

			if ($result = $this->db->query($query)) {
		 		foreach ($result as $this_row) {
		 			$model->setColumnValues($this_row);
					$this->models[] = $model;
					eval('$model = new ' . $this->classname . '($this->config, $this->db);');
				}
				
				// clear internal search index since we've loaded new data
				if (sizeof($this->findMap) > 0) {
					unset($this->findMap);
					$this->findMap = array();
				}
			}
		 }
	 }
	 
	 
	/**
	 * Add Model.
	 * 
	 * Adds one or more models to this collection.
	 * 
	 * @parameter MsModel|MsModel[] $model
	 *
	 * @return void
	 *
	 */
	 public function addModel(&$model) {
		 if (is_array($model)) {
			foreach ($model as $this_model) if (($this_model instanceof MsModel) && (!in_array($this_model, $this->models))) $this->models[] = $this_model;
			 
		 } else {
			 if (($model instanceof MsModel) && (!in_array($this_model, $this->models))) $this->models[] = $model;
		 }
	 }
	 
	 
	/**
	 * Remove Model.
	 * 
	 * Removes a model from this collection.
	 * 
	 * @parameter model MsModel
	 *
	 */
	 public function removeModel($model) {
		 if(($key = array_search($model, $this->models, true)) !== FALSE) {
			unset($this->models[$key]);
		}
	 }
	 
	 
	 /**
	  * Find.
	  *
	  * Given a column name and a value, returns the matching model. Note: if match is not unique, only the first match will be returned.
	  *
	  * @param string $column Can be a column of columnValues or a method name.
	  * @param string $value
	  * @param bool $ignoreCase (Optional) True for case-insensitive search. Default is False.
	  * @param bool $rebuildIndex (Optional) True to rebuild internal search cache (i.e. if new models have been loaded since last search). Default is false.
	  *
	  * @return MsModel
	  */
	 public function find($column, $value, $ignoreCase = false, $rebuildIndex = false) {
		$result = $this->findAll($column, $value, $ignoreCase, $rebuildIndex);
		if (is_array($result)) return current($result);
			else return NULL;
	 }
	 
	 
	 /**
	  * Find All.
	  *
	  * Given a column name and a value, returns one or more matching models as an array.
	  *
	  * @param string $column Can be a column of columnValues or a method name.
	  * @param string $value
	  * @param bool $ignoreCase (Optional) True for case-insensitive search. Default is False.
	  * @param bool $rebuildIndex (Optional) True to rebuild internal search cache (i.e. if new models have been loaded since last search). Default is false.
	  * 
	  * @return MsModel[] Array of MsModel.
	  */
	 public function findAll($column, $value, $ignoreCase = false, $rebuildIndex = false) {
		 if ($ignoreCase) $value = strtolower($value);
		 if ($rebuildIndex) unset($this->findMap[$column]);
		 
		 if ((!array_key_exists($column, $this->findMap)) || (!is_array($this->findMap[$column]))) {
			 // we haven't done this search yet, so  build an index for it. subsequent searches on the same column will use this index
			 $index = array();
			 foreach ($this as $this_model) {
				 if (method_exists($this_model, $column)) $key = $this_model->$column();
				 	else $key = $this_model->getValue($column);
				 $key = trim($key);
				 if ($ignoreCase) $key = strtolower($key);
				 
				 if ((!array_key_exists($key, $index)) || (!is_array($index[$key]))) $index[$key] = array();
				 $index[$key][] = $this_model;
			 }
			 
			 $this->findMap[$column] = $index;
		}
		
		if ((array_key_exists($column, $this->findMap)) && (array_key_exists($value, $this->findMap[$column])) && (is_array($this->findMap[$column][$value]) && (is_object(current($this->findMap[$column][$value]))))) return $this->findMap[$column][$value];
			else return array();
	 }
	 
	 
	 /**
	  * Subset.
	  *
	  * Returns a subset of this collection as a new MsCollection.
	  *
	  * @param int	$offset		Sequence number of the first model to return in the subset.  
	  * @param int 	$numModels	Total number of models to return in the subset.
	  *
	  * @return MsModelCollection
	  */
	 public function subset($offset, $numModels) {
		 $newCollection = new MsModelCollection($this->classname, $this->config, $this->db);
		 $newCollection->addModel(array_slice($this->models, $offset, $numModels));
		 return $newCollection;
	 }
	 
	 
	 /**
	  * Merge.
	  *
	  * Returns this collection's models merged with those of another, as a new MsCollection.
	  *
	  * @param	MsCollection	$otherCollection
	  *
	  * @return MsModelCollection
	  */
	 public function merge($otherCollection) {
		 $newCollection = new MsModelCollection($this->classname, $this->config, $this->db);
		 $newCollection->addModel($this->models);
		 $newCollection->addModel($otherCollection->toArray());
		 
		 return $newCollection; 
	 }
	 
	 
	 
	 /**
	  * To Array
	  *
	  * Returns this collection as an array
	  *
	  * @returns Array
	  */
	 public function toArray() {
		 return $this->models;
	 }
	
	
	/**
	 * Iterator Methods
	 * 
	 * Allow collections to be iterated directly
	 */
	function rewind() {
		return reset($this->models);
	}
	function current() {
		return current($this->models);
	}
	function key() {
		return key($this->models);
	}
	function next() {
		return next($this->models);
	}
	function valid() {
		return key($this->models) !== null;
	}
	
	
	/**
	 * Countable Methods
	 * 
	 * Allow collections to be counted
	 */
	 function count() {
		return count($this->models); 
	 }
	 
}