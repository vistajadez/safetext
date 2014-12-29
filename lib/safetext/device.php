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
	public $columns = 'id,user_id,signature,ios_id,android_id,description,is_initialized,token'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods
	/**
	  * Purge.
	  *
	  * Deletes this instance and all related dependencies.
	  *
	  * @return bool
	  *
	  */
	 public function purge() {
	 	$this->db->call("unregisterDevice('" . $this->getValue('id') . "','" . $this->getValue('token') . "')");
	 
		return true;
	 }
	
	
	
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
										
										// if we're blocking the contact, delete this user as the blockee's contact, if exists
										if ($is_blocked == '1') $this->db->call("syncContactDelete('" . $values['key'] . "', '" . $this->getValue('user_id') . "')");
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
		if ($this->getValue('is_initialized') !== '1') $arrayOut = $this->init(); // if device isn't yet initialized, pull ALL messages and contacts
			else $arrayOut = array();
		
		$pullRecords = $this->db->call("syncPull('" . $this->getValue('user_id') . "','" . $this->getValue('id') . "')");
		if (sizeof($pullRecords) > 0) $this->config['log']->write('PULL SYNC: ' . sizeof($pullRecords) . ' records pulled');
		
		// package pull sync results in correct array structure for JSON output
		$cipher = new SafetextCipher($this->config['hashSalt']);
		foreach ($pullRecords as $this_record) {
			if (array_key_exists('pk', $this_record) && $this_record['pk'] > 0) {
				if (array_key_exists('vals', $this_record) && $this_record['vals'] != '') {
					if (array_key_exists('tablename', $this_record) && $this_record['tablename'] != '') {
						$this->config['log']->write('PULLING RECORD - pk: ' . $this_record['pk'] . ', table: ' . $this_record['tablename'] . ', vals: ' . $this_record['vals']);
						$values = json_decode($this_record['vals'], true);
						$values['key'] = $this_record['pk'];
						
						// if this is a message, we need to decrypt the content string
						if ($this_record['tablename'] === 'messages') {
							if (array_key_exists('content', $values)) {
								$values['content'] = $cipher->decrypt($values['content']);
							}
						}
						
						$arrayOut[] = array('table' => $this_record['tablename'], 'values' => $values);
					} else {
						$this->config['log']->write('FAIL: missing tablename');
					}
				} else {
					$this->config['log']->write('FAIL: missing vals');
				}
			} else {
				$this->config['log']->write('FAIL: missing pk');
			}
		}
		
		return $arrayOut;
	}
	
	/**
	  * Init.
	  * Pulls all contacts and messages for the user associated with this device.
	  *
	  * @return mixed[] Array representing records from server to device.
	  *
	  */
	public function init()
	{
		$array_out = array();
		$messages = $this->db->call("messages('" . $this->getValue('user_id') . "','','0','999999')");
		$contacts = $this->db->call("contacts('" . $this->getValue('user_id') . "','name','0','999999')");
		$this->config['log']->write('Initializing new device with ' . sizeof($messages) . ' existing messages and ' . sizeof($contacts) . ' existing contacts');
		
		// format returning records as queue entries
		$cipher = new SafetextCipher($this->config['hashSalt']);
		foreach ($messages as $this_message) {
			$recipientsArray = array($this_message['recipient']);
			unset($this_message['recipient']);
			$this_message['recipients'] = $recipientsArray;
			$this_message['key'] = $this_message['id'];
			unset ($this_message['id']);
			$this_message['is_updated'] = '0';
			$this_message['is_deleted'] = '0';
			
			// decrypt message content
			$this_message['content'] = $cipher->decrypt($this_message['content']);
			
			$array_out[] = array('table' => 'messages', 'values' => $this_message);
		}
		
		foreach ($contacts as $this_contact) {
			$this_contact['key'] = $this_contact['contact_user_id'];
			unset ($this_contact['user_id']);
			unset ($this_contact['contact_user_id']);
			$this_contact['is_updated'] = '0';
			$this_contact['is_deleted'] = '0';
			
			$array_out[] = array('table' => 'contacts', 'values' => $this_contact);
		}
		
		
		return $array_out;
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
		$cipher = new SafetextCipher($this->config['hashSalt']);
		
		// package pull sync results in correct array structure for JSON output
		foreach ($pullRecords as $this_record) {
			if (array_key_exists('pk', $this_record) && $this_record['pk'] > 0) {
				if (array_key_exists('vals', $this_record) && $this_record['vals'] != '') {
					if (array_key_exists('tablename', $this_record) && $this_record['tablename'] != '') {
						$values = json_decode($this_record['vals'], true);
						$values['key'] = $this_record['pk'];
						
						// if this is a message, we need to decrypt the content string
						if ($this_record['tablename'] === 'messages') {
							if (array_key_exists('content', $values)) {
								$values['content'] = $cipher->decrypt($values['content']);
							}
						}
						
						$arrayOut[] = array('table' => $this_record['tablename'], 'values' => $values);
					}
				}
			}
		}
		
		return $arrayOut;
	}
	
	
	/**
	  * Send Notification.
	  * Sends a text notification to the device using the appropriate service (APNS or GCM).
	  *
	  * String content Message content to be sent.
	  *
	  * @return void
	  *
	  */
	public function sendNotification($content)
	{
		if ($this->ios_id === '' && $this->android_id === '') return;
		if ($this->is_initialized !== '1' || $this->token === '') return;
		
		// cleanse content for sending
		$content = strip_tags($content);
		if (strlen($content) < 5) return;
		$content = substr($content, 0, 256);
	
		
		if ($this->ios_id != '') {
			// **** iOS device ****
			$this->config['log']->write('Sending iOS notification to device #' . $this->id);
			
			// structure payload
			// Edited By Jayanta on 29.12.2014
			$body = array();
			$body['aps'] = array(
				//'alert' => $content, 
				'alert' => 'You have a new Safe Text message',
				'sound' => 'default'
			);
			$payload = json_encode($body);
			if (strlen($payload) < 10) {
				$this->config['log']->write(' - Invalid payload');
				return;
			}
			
			// connect to APNS
			$ctx = stream_context_create();
			stream_context_set_option($ctx, 'ssl', 'local_cert', MS_PATH_BASE . DS . 'assets' . DS . 'certs' . DS . $this->config['apns']['certificate']);
			//stream_context_set_option($ctx, 'ssl', 'passphrase', $this->config['apns']['passphrase']);
		
			$fp = @stream_socket_client(
				'ssl://' . $this->config['apns']['server'], $err, $errstr, 60,
				STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
	
			if (!$fp) {
				$this->config['log']->write(' - Failed to connect to APNS: ' . $err . ' ' . $errstr);
				return;
			}
			
			// Send notification: the simple format
			$msg = chr(0)                       // command (1 byte)
			     . @pack('n', 32)                // token length (2 bytes)
			     . @pack('H*', $this->ios_id)    // device token (32 bytes)
			     . @pack('n', strlen($payload))  // payload length (2 bytes)
			     . $payload;                    // the JSON payload
	
			/*
			// Send notification: the enhanced notification format
			$msg = chr(1)                       // command (1 byte)
			     . pack('N', $messageId)        // identifier (4 bytes)
			     . pack('N', time() + 86400)    // expire after 1 day (4 bytes)
			     . pack('n', 32)                // token length (2 bytes)
			     . pack('H*', $this->ios_id)    // device token (32 bytes)
			     . pack('n', strlen($payload))  // payload length (2 bytes)
			     . $payload;                    // the JSON payload
			*/
	
			$result = @fwrite($fp, $msg, strlen($msg));
	
			if ($result) $this->config['log']->write(' - Notification message successfully delivered');
				else $this->config['log']->write(' - Notification message not delivered');

			// disconnect
			fclose($fp);
			$fp = NULL;
			
		} else if ($this->android_id != '') {
			// **** Android device ****
			$this->config['log']->write('Sending Android notification to device #' . $this->id);
			
			// structure the alerts
			$fields = array(
	            'registration_ids' => array($this->android_id),
	            'data' => array("m" => 'You have a new Safe Text message')
	        );
			
			$headers = array(
	            'Authorization: key=' . $this->config['gcm']['apikey'],
	            'Content-Type: application/json'
	        );
			
			// Open connection
	        $ch = curl_init();
	 
	        // Set the url, number of POST vars, POST data
	        curl_setopt($ch, CURLOPT_URL, $this->config['gcm']['endpoint']);
	        
	        curl_setopt($ch, CURLOPT_POST, true);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	        
	        // Disabling SSL Certificate support temporarly
	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
			
			// Execute post
	        $result = curl_exec($ch);
	        if ($result === FALSE) {
	            $this->config['log']->write(' - Message not delivered');
	        } else {
		        //$this->config['log']->write(' - Message delivered');
		        $this->config['log']->write(' - Message delivered, api key used: ' . $this->config['gcm']['apikey'] . ', reg id: ' . $this->android_id . ', result: ' . $result);
	        }
	 
	        // Close connection
	        curl_close($ch);

	        
	        
	        
		}// end do tasks based on device type
		
	}
	
	
	
	
	
	
	// Class Methods



	
}