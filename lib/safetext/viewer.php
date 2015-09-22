<?php

/**
 * MyApp Viewer Object.
 * An Example of how to set up a class to represent the viewer/user of an application.
 *
 */
class MyappViewer extends MsModel {
	// Database
	public $dbTable = 'viewer'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('id');
	
	// Columns
	public $columns = 'id,first,last,pass,email,pic_filename'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods

	
	
	
	
	// Methods unique to Guest Object
	
	/**
	  * Full Name
	  *
	  * @return String The guest's full name as first + space + last.
	  *
	  */
	 public function fullName() {
		return trim($this->first) . ' ' . trim($this->last);
	 }
	
	
}