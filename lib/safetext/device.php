<?php

/**
 * SafeText Mobile Device Object.
 * Represents an application user's mobile device.
 *
 */
class SafetextDevice extends SafetextModel {
	// Database
	public $dbTable = 'sync_device'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('id');
	
	// Columns
	public $columns = 'id,user_id,signature,description,is_initialized,token'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods

	
	
	
	// Methods unique to Device Object
	/**
	  * Sync.
	  *
	  * Performs a sync operation with the server
	  *
	  * @param mixed[] Array representing records from device to server
	  *
	  * @return mixed[] Array representing records from server to device
	  *
	  */
	public function sync($recordsIn)
	{
		/* PUSH SYNC */	
		// push each record
		foreach ($recordsIn as $this_record) {
			if (!is_array($record)) return;

			if (array_key_exists('table', $record)) $table = strtolower(trim($record['table']));
				else $table = NULL;
			if (array_key_exists('values', $record)) $values = $record['values'];
				else $values = NULL;
			if ($table !== NULL) {
				if (is_array($values)) {
					if (array_key_exists('key', $value) && $values['key'] > 0) {
						if ($table === 'contacts' || $table === 'messages') {
							$this->config['log']->write('PUSHING RECORD, table: ' . $table . ', key: ' . $values['key']);
								if ($table === 'contacts') {
									// push a contact
									if (array_key_exists('is_deleted', $values) && $values['is_deleted'] == '1') {
										// stored procedure call for deleting a contact
										$this->config['log']->write('This is a DELETE');
										$db->query("CALL syncContactDelete('" . $this->getValue('user_id') . "','" . $values['key'] . "')")->fetch_assoc();
									} else if (array_key_exists('is_updated', $values) && $values['is_updated'] == '1') {
										// stored procedure call for adding/updating a contact
										$this->config['log']->write('This is an ADD/UPDATE');
										array_key_exists('name', $values)? $name = $values['name']: $name = '';
										array_key_exists('email', $values)? $email = $values['email']: $email = '';
										array_key_exists('phone', $values)? $phone = $values['phone']: $phone = '';
										array_key_exists('is_whitelist', $values)? $is_whitelist = $values['is_whitelist']: $is_whitelist = '0';
										array_key_exists('is_blocked', $values)? $is_blocked = $values['is_blocked']: $is_blocked = '0';
										$db->query("CALL syncContact('" . $this->getValue('user_id') . "','" . $values['key'] . 
											"','$name','$email','$phone','$is_whitelist','$is_blocked',)")->fetch_assoc();
									}
								} else if ($table === 'messages') {
									// TODO
									
									
									
									
								}
				
				
						} // end if table is supported
					} // end if key exists
				} // end if values are correctly specified
			} // end if table is specified	
		} // end iterating through each push record
		
		
		/* PULL SYNC */	
		
		
	
		return array();
	}
	
	
	
	
	
	
	
	
	
	// Class Methods



	
}