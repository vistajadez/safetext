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
	  * Performs a sync operation with the server.
	  *
	  * @param mixed[] Array representing records from device to server.
	  *
	  * @return mixed[] Array representing records from server to device.
	  *
	  */
	public function sync($recordsIn)
	{
		/* PUSH SYNC */	
		if (is_array($recordsIn)) {
			// push each record
			foreach ($recordsIn as $record) {
				if (!is_array($record)) return array();
	
				if (array_key_exists('table', $record)) $table = strtolower(trim($record['table']));
					else $table = NULL;
				if (array_key_exists('values', $record)) $values = $record['values'];
					else $values = NULL;
				if ($table !== NULL) {
					if (is_array($values)) {
						if (array_key_exists('key', $values) && $values['key'] > 0) {
							if ($table === 'contacts' || $table === 'messages') {
							
								$this->config['log']->write('PUSHING RECORD, table: ' . $table . ', key: ' . $values['key']);
								if ($table === 'contacts') {
									// push a contact
									if (array_key_exists('is_deleted', $values) && $values['is_deleted'] == '1') {
										// stored procedure call for deleting a contact
										$this->config['log']->write('This is a DELETE');
										$this->db->call("syncContactDelete('" . $this->getValue('user_id') . "','" . $values['key'] . "')");
									} else if (array_key_exists('is_updated', $values) && $values['is_updated'] == '1') {
										// stored procedure call for adding/updating a contact
										$this->config['log']->write('This is an ADD/UPDATE');
										array_key_exists('name', $values)? $name = $values['name']: $name = '';
										array_key_exists('email', $values)? $email = $values['email']: $email = '';
										array_key_exists('phone', $values)? $phone = $values['phone']: $phone = '';
										array_key_exists('is_whitelist', $values)? $is_whitelist = $values['is_whitelist']: $is_whitelist = '0';
										array_key_exists('is_blocked', $values)? $is_blocked = $values['is_blocked']: $is_blocked = '0';
										$this->db->call("syncContact('" . $this->getValue('user_id') . "','" . $values['key'] . 
											"','$name','$email','$phone','$is_whitelist','$is_blocked')");
									}
								} else if ($table === 'messages') {
									// push a message
									if (array_key_exists('is_deleted', $values) && $values['is_deleted'] == '1') {
										// stored procedure call for deleting a message
										$this->config['log']->write('This is a DELETE');
										$this->db->call("syncMessageDelete('" . $values['key'] . "')");
									} else if (array_key_exists('is_updated', $values) && $values['is_updated'] == '1') {
										// stored procedure for updating a message
										$this->config['log']->write('This is an UPDATE');
										array_key_exists('is_important', $values)? $is_important = $values['is_important']: $is_important = '0';
										array_key_exists('is_draft', $values)? $is_draft = $values['is_draft']: $is_draft = '0';
										array_key_exists('is_read', $values)? $is_read = $values['is_read']: $is_read = '0';
										$this->db->call("syncMessage('" . $this->getValue('user_id') . "','" . $values['key'] . 
											"','$is_important','$is_draft','$is_read')");
									}									
								}
					
							} // end if table is supported
						} // end if key exists
					} // end if values are correctly specified
				} // end if table is specified	
			} // end iterating through each push record
		} // end if push records are an array
		
		/* PULL SYNC */	
		$arrayOut = array();
		$pullRecords = $this->db->call("syncPull('" . $this->getValue('user_id') . "','" . $this->getValue('id') . "')");
		
		// package pull sync results in correct array structure for JSON output
		foreach ($pullRecords as $this_record) {
			if (array_key_exists('pk', $this_record) && $this_record['pk'] > 0) {
				if (array_key_exists('vals', $this_record) && $this_record['vals'] != '') {
					if (array_key_exists('tablename', $this_record) && $this_record['tablename'] != '') {
						$values = json_decode($this_record['vals'], true);
						$values['key'] = $this_record['pk'];
						
						$arrayOut[] = array('table' => $this_record['tablename'], 'values' => $values);
					}
				}
			}
		}
		
		return $arrayOut;
	}
	
	
	/**
	  * Last Pull.
	  * Retrieves the records that were sent to this device in the most recent sync.
	  *
	  * @param mixed[] Array representing records from device to server.
	  *
	  * @return mixed[] Array representing records from server to device.
	  *
	  */
	public function lastpull()
	{
		$arrayOut = array();
		$pullRecords = $this->db->call("syncLastPull('" . $this->getValue('user_id') . "','" . $this->getValue('id') . "')");
		
		// package pull sync results in correct array structure for JSON output
		foreach ($pullRecords as $this_record) {
			if (array_key_exists('pk', $this_record) && $this_record['pk'] > 0) {
				if (array_key_exists('vals', $this_record) && $this_record['vals'] != '') {
					if (array_key_exists('tablename', $this_record) && $this_record['tablename'] != '') {
						$values = json_decode($this_record['vals'], true);
						$values['key'] = $this_record['pk'];
						
						$arrayOut[] = array('table' => $this_record['tablename'], 'values' => $values);
					}
				}
			}
		}
		
		return $arrayOut;
	}
	
	
	
	
	
	
	
	
	// Class Methods



	
}