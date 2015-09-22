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
										//if ($is_blocked == '1') $this->db->call("syncContactDelete('" . $values['key'] . "', '" . $this->getValue('user_id') . "')");
										if ($is_blocked == '1') $this->db->call("syncBlockContactDelete('" . $values['key'] . "', '" . $this->getValue('user_id') . "')");
									}
								} else if ($table === 'messages') {
									// push a message
									if(!array_key_exists('group_id', $values) || $values['group_id'] == "") {
									
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
									else if(array_key_exists('group_id', $values) || $values['group_id'] != ""){
									
										if (array_key_exists('is_deleted', $values) && $values['is_deleted'] == '1') {
											// stored procedure call for deleting a message
											$this->config['log']->write('This is a DELETE');
											$count_participants = $this->db->call("getCountParticipants('" . $values['group_id'] . "')");
											
											$this->db->call("syncGroupMessageDelete('" . $values['key'] . "','".$count_participants[0]['participants_id']."')");
										} else if (array_key_exists('is_updated', $values) && $values['is_updated'] == '1') {
											// stored procedure for updating a message
											$this->config['log']->write('This is an UPDATE');
											array_key_exists('is_important', $values)? $is_important = $values['is_important']: $is_important = '0';
											array_key_exists('is_draft', $values)? $is_draft = $values['is_draft']: $is_draft = '0';
											array_key_exists('is_read', $values)? $is_read = $values['is_read']: $is_read = '0';
											array_key_exists('sender', $values)? $sender = $values['sender']: $sender = '0';											
											
											$count_participants = $this->db->call("getCountParticipants('" . $values['group_id'] . "')");
											$receipients = explode(",",$count_participants[0]['participants_id']);
											$tot_receipients = count($receipients);	
											
												
											
											$sender_id = $this->db->call("getGroupsenderId('" . $values['key'] . "')");	
											
											$last_msg_id = $this->db->call("getGroupLastMessage('" . $values['group_id'] . "')");	
											
											$this->db->call("UpdateCountIsRead('".$values['key']."','".$this->getValue('user_id')."','".$sender_id[0]['sender_id']."')");																											
											if($sender_id[0]['sender_id']!=$this->getValue('user_id')) {
											
											$this->db->call("syncResponseGroupMessage('".$values['group_id']."','".$values['key'].
												"','$is_important','$is_draft','$is_read','$tot_receipients','".$count_participants[0]['participants_id']."','".$sender_id[0]['sender_id']."','".$sender_id[0]['username']."','".$this->getValue('user_id')."')");
											
											}	
																																												
											$this->db->call("syncGroupMessage('".$values['group_id']."','".$values['key'].
												"','$is_important','$is_draft','$is_read','$tot_receipients','".$count_participants[0]['participants_id']."','".$sender_id[0]['sender_id']."','".$sender_id[0]['username']."')");
												
												
										}
										
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
		//$arrayOut[] = array('table' =>  $this->getValue('user_id'),'device' =>  $this->getValue('id'), 'values' => $pullRecords);
		//$resultOut = array_unique($arrayOut);
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
		$contacts = $this->db->call("contacts('" . $this->getValue('user_id') . "')");
		$groups = $this->db->call("getGroupDetails('" . $this->getValue('user_id') . "')");
		$group_messages = $this->db->call("getGroupMessages('" . $this->getValue('user_id') . "')");
		
		
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
		
		foreach ($groups as $this_group) {
		    $username = "";
			//$arr_username = array();
			$user_group_name = $this->db->call("checkGroupname('".$this_group['id']."','".$this->getValue('user_id')."')");
			if($user_group_name[0]['group_name']!="") {
				$group['group_name'] = $user_group_name[0]['group_name'];
			}
			else {
				$group['group_name'] = $this_group['group_name'];
			}
			$group['group_id'] = $this_group['id'];
			$group['participants_id'] = array($this_group['participants_id']);
			
			$participants = explode(",",$this_group['participants_id']);
			$key = array_search($this->getValue('user_id'), $participants);
			unset($participants[$key]);
							
			$participants = array_values($participants);
			$count_p = count($participants);
			
			for($i=0;$i<$count_p;$i++) {
			
				$f_username = $this->db->call("getGroupUsername('".$participants[$i]."','".$this->getValue('user_id')."')");
				if($f_username[0]['name']!="")
				$username .= $f_username[0]['name'];
				else
				$username .= $f_username[0]['username'];
				if($i<($count_p-1))
				$username .= ",";
			}
			//$username = implode(",",$arr_username);
			
			$group['participants_name'] = $username;
			
			$array_out[] = array('table' => 'groups', 'values' => $group);
		}
		
		foreach ($group_messages as $this_group_messages) {
			//$group_message['group_name'] = $this_group_messages['group_name'];
			//$group_message['sender_id'] = $this_group_messages['sender_id'];
			
			$message = $this->db->call("getMessageDetails('" . $this_group_messages['message_id'] . "')");
			
			if($message[0]['id']!="") {
						
			$group_message['group_id'] = $this_group_messages['id'];
			$group_message['content'] = @$cipher->decrypt($message[0]['content']);
			$group_message['image'] = $message[0]['image'];
			
			$chk_is_read = $this->db->call("CheckIsreadUser('".$this_group_messages['message_id']."','".$this->getValue('user_id')."')");
			
			if($message[0]['is_read']==0) {
				if($chk_is_read[0]['count_read']==0) {			
				$group_message['is_read'] = "0";
				}
				else {
				$group_message['is_read'] = "1";
				}
			}
			else {
				$group_message['is_read'] = $message[0]['is_read'];
			}	
			$group_message['is_important'] = $message[0]['is_important'];
			$group_message['is_draft'] = $message[0]['is_draft'];
			$group_message['sent_date'] = $message[0]['sent_date'];
			$group_message['read_date'] = $message[0]['read_date'];
			$group_message['expire_date'] = $message[0]['expire_date'];
			
			
			$group_message['sender'] = $this_group_messages['sender_id'];
			//$sender_name = $this->db->call("getSenderName('" . $this_group_messages['sender_id'] . "')");
			$f_sendername = $this->db->call("getGroupUsername('".$this_group_messages['sender_id']."','".$this->getValue('user_id')."')");
			if($f_sendername[0]['name']!="")
			$sendername = $f_sendername[0]['name'];
			else
			$sendername = $f_sendername[0]['username'];
			$group_message['sender_name'] = $sendername;
			$group_message['recipients'] = array($this_group_messages['participants_id']);
			/*
			$f_username = $this->db->call("getGroupUsername('".$this_group_messages['participants_id']."')");
			foreach($f_username as $this_username) {
					$arr_username[] = $this_username['username'];
			}
			$username = implode(",",$arr_username);
			$group_message['participants_name'] = $username;*/
			
			$group_message['key'] = $this_group_messages['message_id'];
			$group_message['is_updated'] = '0';
			$group_message['is_deleted'] = '0';
			
			$array_out[] = array('table' => 'messages', 'values' => $group_message);
			
			}
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
	public function sendNotification($content,$count="",$rcv_id="")
	{
		if ($this->ios_id === '' && $this->android_id === '') return;
		if ($this->is_initialized !== '1' || $this->token === '') return;
		
		// cleanse content for sending
		$content = strip_tags($content);
		if (strlen($content) < 5) return;
		$content = substr($content, 0, 256);
	
		$badge = intval($count);
		
		if ($this->ios_id != '') {
			// **** iOS device ****
			$this->config['log']->write('Sending iOS notification to device #' . $this->id);
			
			// structure payload
			$body = array();
			$body['aps'] = array(
				//'alert' => $content, 
				'alert' => $content,
				'badge' => $badge,
				'sound' => 'default',
				'content-available' => 1				
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
	            'data' => array("m" => $content)
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