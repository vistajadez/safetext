<?php

/**
 * Controller for Webservices REST API version 1.
 *
 *
 * This is the controller for the SafeText Webservices REST API, version 1.
 *
 */
class ApiController extends MsController {
	
	/**
	 * Default Action.
	 * 
	 * Called when no action is defined.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
	 	
	 	// ensure we're using https
		if (MS_PROTOCOL !== 'https') {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		} else {	 
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'No action specified'));
		}
	 }
	 
	 public function lifetimeAction(&$viewObject) {
	 	
	 	$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
	 	// ensure we're using http
	 	array_key_exists('user_id', $this->params)? $user_id= $this->params['user_id']: $user_id= '';
		array_key_exists('lifetime', $this->params)? $lifetime= $this->params['lifetime']: $lifetime= '';
		
		$result = current($db->query("UPDATE users SET expiretime='$lifetime' WHERE id='$user_id'"));
		echo "Success";
	 }
	 
	 public function bubblecolorAction(&$viewObject) {
	 	
	 	$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
	 	// ensure we're using http
	 	array_key_exists('user_id', $this->params)? $user_id= $this->params['user_id']: $user_id= '';
		array_key_exists('color', $this->params)? $color= $this->params['color']: $color= '';
		$check = current($db->query("SELECT id FROM bubble_color WHERE user_id = '$user_id'"));
		if($check[0]['id'] == "") {
                $sql_insert = "INSERT INTO bubble_color(user_id,color) VALUES($user_id,'$color')";
		$result = current($db->query($sql_insert));
                }
		else
		$result = current($db->query("UPDATE bubble_color SET color='$color' WHERE user_id='$user_id'"));
		echo "Success";
	 }

	/**
	 * Auth Action.
	 * 
	 * SafeText is a secure application and it is important to authenticate all API calls with a unique token obtained from the server
	 * by providing a user's account username and password. This authentication step effectively logs the mobile app user into SafeText.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Authentication
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function authAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_USERNAME', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_USERNAME'] !== '') {
					if (array_key_exists('HTTP_X_SAFETEXT_PASSWORD', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_PASSWORD'] !== '') {
						if (array_key_exists('device_signature', $this->params) && $this->params['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->params['device_description'] !== '') {
								
								array_key_exists('ios_id', $this->params)? $ios_id = $this->params['ios_id']: $ios_id = '';
								array_key_exists('android_id', $this->params)? $android_id = $this->params['android_id']: $android_id = '';
							
								if ($this->params['device_signature'] === 'webclient' || $ios_id !== '' || $android_id !== '') {								
								
									// Create a database connection to share
									$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
				
									// generate token via db stored procedure
									require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
									$tokenDetails = SafetextUser::generateToken($_SERVER['HTTP_X_SAFETEXT_USERNAME'], md5($_SERVER['HTTP_X_SAFETEXT_PASSWORD']), $this->params['device_signature'], $this->params['device_description'], $ios_id, $android_id, $db, $this->config);
									
									if ($tokenDetails['id'] > 0) {

                                                                                //set panic to zero
										$updatePanic = current($db->CALL("UpdatePanicButton('".$tokenDetails['id']."')"));

									
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('data', array('token' => $tokenDetails['token'], 'user' => $tokenDetails['id']));
										
										// log the auth request
										$this->config['log']->write('User: ' . $tokenDetails['id'] . ', Token: ' . $tokenDetails['token'] . ' (' . $this->params['device_description'] . ')', 'Auth Request');
			
										
									} else { // unsuccessful auth token generation
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' => $tokenDetails['msg']));
									}
								
								} else { // missing notifications token
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'An iOS id  or Android id is required, for device notification support'));
								}
							} else { // no device description
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Missing device description'));
							}
						} else { // no device sig
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Missing device signature'));
						}
					} else { // no password
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing SafeText password'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText username'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for auth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Expire Auth Action.
	 * 
	 * When a user opts to logout, the server will expire the auth token and the mobile app should remove the auth token from the device 
	 * completely. The user will be required to login again (authenticate again) for the mobile app to make subsequent API calls.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Authentication
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function expireauthAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('token', $this->params) && $this->params['token'] !== '') {
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// expire token via db stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					SafetextUser::expireToken($this->params['token'], $db, $this->config);
				
					$viewObject->setValue('status', 'success');
					
					// log the expireauth request
					$this->config['log']->write('Token: ' . $tokenDetails['token'], 'Expire Auth Request');

				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing auth token'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for expireauth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Devices Action.
	 * 
	 * DELETE request completely deletes a user's device from SafeText.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Authentication
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function devicesAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'DELETE') {
				$deviceId = (array_key_exists('HTTP_X_SAFETEXT_DEVICEID', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_DEVICEID'] !== '') ? $_SERVER['HTTP_X_SAFETEXT_DEVICEID'] : '';
				$deviceToken = (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') ? $_SERVER['HTTP_X_SAFETEXT_TOKEN'] : '';
			
				if ($deviceId != '' || $deviceToken != '') {
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
			
					if ($deviceId != '') {
						// delete device using ID via stored procedure
						$db->CALL("unregisterDevice('$deviceId', '')");
						$viewObject->setValue('status', 'success');
							
						// log the request
							$this->config['log']->write('Device: ' . $deviceId, 'Unregister Device Request');
						
						return; 
					}
			
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($deviceToken, $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							$user->getRelationship('device')->purge();
							
							$viewObject->setValue('status', 'success');
							
							// log the request
							$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), Device: ' . $user->getRelationship('device')->id, 'Unregister Device Request');

						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no token
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing auth token or device ID'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
		
		
	/**
	 * Messages Action.
	 * 
	 * Messages are sent from one user to another using the Send Messages web service. 
	 * They will be received by the recipient during the recipient user's next device sync.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sending-Messages
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function messagesAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if (array_key_exists('recipients', $this->params) && is_array($this->params['recipients'])) {
								if (array_key_exists('content', $this->params)) {
									if (strlen($this->params['content']) <= $this->config['maxMessageLength']) {
										// check all message options
										array_key_exists('is_important', $this->params)? $is_important = $this->params['is_important']: $is_important = '0';
										array_key_exists('is_draft', $this->params)? $is_draft = $this->params['is_draft']: $is_draft = '0';
										array_key_exists('lifetime', $this->params)? $lifetime = $this->params['lifetime']: $lifetime = '1440';
										if ($lifetime > 1440) $lifetime = 1440; // message lifetime cannot be more than 24 hrs
										array_key_exists('image', $this->params)? $image = $this->params['image']: $image = '';
										
										// log the send message request
										$this->config['log']->write('User: ' . $user->id . ", Device: " . $user->getRelationship('device')->id . ", image: " . $image, 'Send Message Request');
										$this->config['log']->write('Request body: ' . file_get_contents('php://input'));
									
										// execute send via stored procedure
										$cipher = new SafetextCipher($this->config['hashSalt']);
										$result = current($db->call("sendMessage('" . $user->id . "','" . current($this->params['recipients']) . "','" . $this->escapeForDb($cipher->encrypt($this->params['content'])) . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "','" . $image . "')"));
										
										if ($result['key'] > 0) {
											$this->config['log']->write('Successfully delivered. Message key ' . $result['key']);
											
											// send device notification(s) to recipient
											$recipientSettings = current($db->CALL("getSettings('" . current($this->params['recipients']) . "')"));
											
											
											if ($recipientSettings['notifications_on'] == '1') {
												// load all registered devices
												$devicesArray = $db->call("devices('" . current($this->params['recipients']) . "')");
												$devices = new SafetextModelCollection('SafetextDevice', $this->config, $db);
												$devices->load($devicesArray);
												//foreach ($devices as $this_device) $this_device->sendNotification($user->fullName() . ': ' . $this->params['content']);
												
												foreach ($devices as $this_device) 
												{
												//$val_is_read = current($db->CALL("getisread('" .current($this->params['recipients']). "')"));
												$value_unread = current($db->CALL("getgroupisread('" .current($this->params['recipients']). "')"));
											
												$badge = $value_unread['tot_unread'];
												
												$this_device->sendNotification('You have a new Safe Text',$badge,current($this->params['recipients']));
												}	
											}
											
											// load successful output into view
											$viewObject->setValue('status', 'success');
											$viewObject->setValue('token', $user->getRelationship('device')->token);
											$viewObject->setValue('data', array('key' => $result['key']));
										} else {
											// load error message output into view
											$viewObject->setValue('status', 'fail');
											$viewObject->setValue('token', $user->getRelationship('device')->token);
											$viewObject->setValue('data', array('message' => $result['msg']));
											$this->config['log']->write('Fail: ' . $result['msg']);
										}
					
					
									} else { // content exceeds max message length
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('message' => 'Message length cannot exceed ' . $this->config['maxMessageLength']));
									}
								} else { // no content
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('token', $user->getRelationship('device')->token);
									$viewObject->setValue('data', array('message' => 'Empty message'));
									$this->config['log']->write('Fail: Empty message');
								}
							} else { // no recipients listed
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Recipient list should be passed as an array'));
								$this->config['log']->write('Fail: Recipient list should be passed as an array');
							}
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/** Group Message
	 */
	 
	 public function groupmessagesAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if (array_key_exists('group_id', $this->params)) {
								if (array_key_exists('content', $this->params)) {
									if (strlen($this->params['content']) <= $this->config['maxMessageLength']) {
										
										array_key_exists('is_important', $this->params)? $is_important = $this->params['is_important']: $is_important = '0';
										array_key_exists('is_draft', $this->params)? $is_draft = $this->params['is_draft']: $is_draft = '0';
										array_key_exists('lifetime', $this->params)? $lifetime = $this->params['lifetime']: $lifetime = '1440';
										if ($lifetime > 1440) $lifetime = 1440; // message lifetime cannot be more than 24 hrs
										array_key_exists('image', $this->params)? $image = $this->params['image']: $image = '';
										
										// log the send message request
										$this->config['log']->write('User: ' . $user->id . ", Device: " . $user->getRelationship('device')->id . ", image: " . $image, 'Send Group Message Request');
										$this->config['log']->write('Request body: ' . file_get_contents('php://input'));
										
										// get participants id from group id
										$participants = current($db->query("SELECT participants_id FROM groups WHERE id = '".$this->params['group_id']."'"));
										$participants_a = explode(",",$participants[0]['participants_id']);
										
										$count = count($participants_a);
										
										$key = array_search($user->id, $participants_a);
										unset($participants_a[$key]);
										
										$sql_username = "SELECT `username` FROM `users` WHERE `id` IN (".$participants[0]['participants_id'].")";
										$f_username = current($db->query($sql_username));
										
										foreach($f_username as $this_username) {
											$arr_username[] = $this_username['username'];
										}
										$username = implode(",",$arr_username);
										
										$sql_sender_username = "SELECT `username` FROM `users` WHERE `id`='".$user->id."'";
										$f_sender_username = current($db->query($sql_sender_username));
										
										// execute send via stored procedure
										$cipher = new SafetextCipher($this->config['hashSalt']);
										$result = current($db->call("sendGroupMessage('" . $user->id . "','" . $f_sender_username[0]['username'] . "','".$participants[0]['participants_id']."','" . $this->params['group_id'] . "','" . $this->escapeForDb($cipher->encrypt($this->params['content'])) . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "','" . $image . "')"));
										
										/*send notification*/
										for($i=0;$i<$count;$i++) {
										
										
										$recipientSettings = current($db->CALL("getSettings('" .$participants_a[$i]. "')"));
										
										if ($recipientSettings['notifications_on'] == '1') {
										
										$devicesArray = $db->call("devices('".$participants_a[$i]."')");
										$devices = new SafetextModelCollection('SafetextDevice', $this->config, $db);
										$devices->load($devicesArray);		

											foreach ($devices as $this_device) {
											
												$value_unread = current($db->CALL("getgroupisread('" .$participants_a[$i]. "')"));
												
												$badge = $value_unread['tot_unread'];
												
												$this_device->sendNotification('You have a new Safe Text',$badge);
							 
											}	
										
										}
										
										}
										/*notification end*/
										
										// load successful output into view
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('key' => $result['key']));
									}
									else { // content exceeds max message length
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('message' => 'Message length cannot exceed ' . $this->config['maxMessageLength']));
									}
								}	
								else { // no content
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('token', $user->getRelationship('device')->token);
									$viewObject->setValue('data', array('message' => 'Empty message'));
									$this->config['log']->write('Fail: Empty message');
								}
							}
							else { // no recipients listed
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Group Id Empty'));
								$this->config['log']->write('Fail: Group Id Empty');
							}
						}
						else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					}
					else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				}
				else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			}
			else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		}
		else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}	
	 
	 }
	  
	 
	
	/**
	 * Sync Action.
	 * 
	 * SafeText facilitates messaging between two users. When one user adds another user as a contact, messages can then be sent 
	 * between the two users. The SafeText web service maintains every user's contact list (the list of users added to their account 
	 * as contacts), as well as every user's active messages.
	 *
	 * When new users are added to a contact list on the web client or messages are sent from the web client, this information 
	 * needs to be sent to the appropriate users' mobile app on their device(s). Likewise, such updates that take place on the mobile app 
	 * need to be sent to the web service.
	 *
	 * Synchronization is a two-way protocol that is always initiated by the mobile app.
	 *
	 * Synchronization begins with the mobile app sending a sync request to the web client REST server. All records which have been 
	 * added, edited, or marked for deletion since the last sync should be passed in this request. The server then returns a JSON response 
	 * containing any records which have been added, edited, or deleted on the web client.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sync-Protocol
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function syncAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							// execute sync (pass mobile device records to server and obtain records to send to mobile device)
							$recordsOut = $user->getRelationship('device')->sync($this->params['data']);
							
							// log the sync request ONLY IF SOMETHING RELEVANT IS BEING PASSED/RECEIVED
							if (sizeof($this->params['data']) > 0) {
								$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), Device: ' . $user->getRelationship('device')->id . ', Data: ' . json_encode($this->params['data']), 'Sync Request');
							}
							
							// log the output
							if (sizeof($recordsOut) > 0) $this->config['log']->write('Records Out: ' . json_encode($recordsOut));

							// load output into view
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', $recordsOut);
					
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for sync'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Last Pull Action.
	 * 
	 * To re-request the previous sync response from the server (i.e. if the response was not processed properly for some reason, 
	 * such as connectivity interruption), it can easily be obtained using the following REST URL: https://safe-text.us/api/lastpull/. 
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Sync-Protocol
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function lastpullAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
						
							// execute sync last pull recall
							$recordsOut = $user->getRelationship('device')->lastpull();
							// log the output
							if (sizeof($recordsOut) > 0) $this->config['log']->write('Records Out: ' . json_encode($recordsOut));

							// load output into view
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', $recordsOut);
					
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}


	/**
	 * Settings Action.
	 * 
	 * User settings are stored on the server and can be get/put by the mobile app using the Settings web service
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Settings
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function settingsAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
				// Create a database connection to share
				$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
							
				// authenticate token with stored procedure
				require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
				$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
		
				if ($user instanceof SafetextUser && $user->isValid()) {
			
					if (MS_REQUEST_METHOD === 'GET') {
						/* GET SETTINGS */
						// Get settings via stored procedure
						$result = current($db->CALL("getSettings('" . $user->id . "')"));
					
						// load output into view
						$viewObject->setValue('status', 'success');
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						$viewObject->setValue('timezone', date_default_timezone_get());
						$viewObject->setValue('timezone_short', date('T'));
						$viewObject->setValue('data', $result);
						
						// log the request
						$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), Settings: ' . json_encode($result), 'Get Settings Request');
							
					} else if (MS_REQUEST_METHOD === 'POST') {
						/* PUT SETTINGS */
						array_key_exists('username', $this->params)? $username = $this->escapeForDb($this->params['username']): $username = '';
						array_key_exists('firstname', $this->params)? $firstname = $this->escapeForDb($this->params['firstname']): $firstname = '';
						array_key_exists('lastname', $this->params)? $lastname = $this->escapeForDb($this->params['lastname']): $lastname = '';
						array_key_exists('email', $this->params)? $email = $this->escapeForDb($this->params['email']): $email = '';
						array_key_exists('phone', $this->params)? $phone = $this->escapeForDb($this->params['phone']): $phone = '';
						array_key_exists('pass', $this->params)? $pass = $this->escapeForDb($this->params['pass']): $pass = '';
						array_key_exists('language', $this->params)? $language = $this->params['language']: $language = 'en';
						array_key_exists('notifications_on', $this->params)? $notifications_on = $this->params['notifications_on']: $notifications_on = '0';
						array_key_exists('whitelist_only', $this->params)? $whitelist_only = $this->params['whitelist_only']: $whitelist_only = '0';
						array_key_exists('enable_panic', $this->params)? $enable_panic = $this->params['enable_panic']: $enable_panic = '0';
						array_key_exists('notes', $this->params)? $notes = $this->params['notes']: $notes = '';


                                                array_key_exists('timedelay', $this->params)? $timedelay = $this->params['timedelay']: $timedelay = '0';
						array_key_exists('fonts', $this->params)? $fonts = $this->params['fonts']: $fonts = 'Small';
						array_key_exists('bubblecolor', $this->params)? $bubblecolor = $this->params['bubblecolor']: $bubblecolor = 'Default';
						array_key_exists('expiretime', $this->params)? $expiretime = $this->params['expiretime']: $expiretime = '1440';

						if (strlen($username) > 3 && strlen($username) < 17) {
							// Put settings via stored procedure
							$cipher = new SafetextCipher($this->config['hashSalt']);
							
							$result = current($db->CALL("putSettings('" . $user->id . "','$username','$firstname','$lastname','$email','$phone','".md5($pass)."','$language','$notifications_on','$whitelist_only','$enable_panic','$notes','$timedelay','$fonts','$bubblecolor','$expiretime')"));

                                                        /* For enabling Panic Button */
							
							if($enable_panic==1) {
								$groupsArray = $db->call("getGroupDetails('" . $user->id . "')");
								foreach ($groupsArray as $this_group) {
									$participants_id = explode(",",$this_group['participants_id']);
									$new_participants_id = array_diff($participants_id,array($user->id));
									$cur_participants = implode(",",$new_participants_id);
									
									$resultUpdate = current($db->CALL("UpdateParticipants('" . $cur_participants . "','".$this_group['id']."')"));	

                                                                        // Send Group Name to Sync
									
									$array_cur_participants = explode(",",$cur_participants);
									$participants = array_values($array_cur_participants);
									//print_r($participants);
									$count = count($participants);
									
									for($k=0;$k<$count;$k++) {
							
									$o_participant = array();
									$o_participant = array_diff($participants, array($participants[$k]));
									
									//echo $participants[$k];
									//print_r($o_participant);
									$count_username = count($participants);	
									
									$username1 = array();
									for($j=0;$j<$count_username;$j++) {
							
									//echo "getGroupUsername('".$o_participant[$j]."','".$participants[$k]."')";
									$f_username = current($db->call("getGroupUsername('".$o_participant[$j]."','".$participants[$k]."')"));
									if($f_username['name']!="")
									$username1[] = $f_username['name'];
									else if($f_username['username']!="")
									$username1[] = $f_username['username'];
									}
									
									$username1 = implode(",",$username1);
							
									$user_group_name = current($db->call("checkGroupname('".$this_group['id']."','".$participants[$k]."')"));
									if($user_group_name['group_name']!="") {
										$group_name = $user_group_name['group_name'];
									}
									else {
										$sql_group_name = "SELECT `group_name` FROM groups WHERE `id`='".$this_group['id']."'";
										$f_group_name = current($db->query($sql_group_name));
									
										$group_name = $f_group_name[0]['group_name'];
									}
										//echo $group_name;
										
										$result = current($db->call("syncGroupContactDelete('".$group_name."','".$this_group['id']."','".$participants[$k]."','".$cur_participants."','".$username1."')"));
							
									}	
									
									//End Sync								
								}
								// call enablePanicButton procedure
								$deleteAll = current($db->CALL("enablePanicButton('" . $user->id . "')"));
								
							}
							
							/* End */

							if (!$result['msg']) {
								$viewObject->setValue('status', 'success');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								
								// log the request
								$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), Settings: ' . $user->id . "','$username','$firstname','$lastname','$email','$phone','$pass','$language','$notifications_on','$whitelist_only','$enable_panic')", 'Put Settings Request');
							} else {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => $result['msg']));
							}
							
						} else {
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', array('message' => 'Username must be between 3 and 16 characters in length'));
						}
					} else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
					}
				} else { // invalid token
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
				}
			} else { // no token
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => 'Missing auth token'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Contacts Lookup Action.
	 * 
	 * SafeText users send messages to their contacts. Every contact is another SafeText user. In order to add contacts to their
	 * contact list, the users' mobile app must be able to perform a query at the web service to retrieve records that match a
	 * search criteria. Currently, the only supported search criteria is full name.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Contacts-Lookup
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactsAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'GET') {
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if (array_key_exists('q', $this->params) && $this->params['q'] !== '') {
						
									// execute contacts lookup stored DB procedure
									$recordsOut = $db->CALL("contactLookup('" . $user->id . "','" . $this->params['q'] . "')");
		
									// load output into view
									$viewObject->setValue('status', 'success');
									$viewObject->setValue('token', $user->getRelationship('device')->token);
									$viewObject->setValue('data', $recordsOut);
					
							} else { // no query string
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Missing search query'));
							}
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Users Action.
	 * 
	 * POST request creates a new user in SafeText. Only allowed from Web Client.
	 * DELETE request deletes an existing user from SafeText.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function usersAction(&$viewObject) { 
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				// **** CREATE USER **** 
				if (array_key_exists('HTTP_X_SAFETEXT_USERNAME', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_USERNAME'] !== '') {
					if (array_key_exists('HTTP_X_SAFETEXT_PASSWORD', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_PASSWORD'] !== '') {
						if (array_key_exists('device_signature', $this->params) && $this->params['device_signature'] !== '') {
							if (array_key_exists('device_description', $this->params) && $this->params['device_description'] !== '') {
								if (array_key_exists('name', $this->params) && $this->params['name'] !== '') {
									if (strlen($_SERVER['HTTP_X_SAFETEXT_USERNAME']) < 17 && strlen($_SERVER['HTTP_X_SAFETEXT_USERNAME']) > 2) {
										array_key_exists('ios_id', $this->params)? $ios_id = $this->params['ios_id']: $ios_id = '';
										array_key_exists('android_id', $this->params)? $android_id = $this->params['android_id']: $android_id = '';
									
										if ($this->params['device_signature'] === 'webclient' || $ios_id !== '' || $android_id !== '') {
									
											// Create a database connection to share
											$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
						
											// generate token via db stored procedure
											array_key_exists('email', $this->params)? $email = $this->escapeForDb($this->params['email']): $email = '';
											
											require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
											$tokenDetails = SafetextUser::newUser($_SERVER['HTTP_X_SAFETEXT_USERNAME'], md5($_SERVER['HTTP_X_SAFETEXT_PASSWORD']), $this->params['name'], $email, $this->params['device_signature'], $this->params['device_description'], $ios_id, $android_id, $db, $this->config);
											
											if ($tokenDetails['id'] > 0) {
											
												$datetime = new DateTime();
												$datetime->modify('+1 months');
								
												$sql_account_status = "UPDATE `users` SET `subscription_level`='30',`subscription_expires`='".$datetime->format('Y-m-d')."' WHERE `username`='".$_SERVER['HTTP_X_SAFETEXT_USERNAME']."'";
												$q_account_status = current($db->query($sql_account_status));
											
												$viewObject->setValue('status', 'success');
												$viewObject->setValue('data', array('token' => $tokenDetails['token'], 'user' => $tokenDetails['id']));
												
											} else { // unsuccessful auth token generation
												$viewObject->setValue('status', 'fail');
												$viewObject->setValue('data', array('message' => $tokenDetails['msg']));
											}
											
										} else { // missing notification ID/token
											$viewObject->setValue('status', 'fail');
											$viewObject->setValue('data', array('message' => 'An iOS id  or Android id is required, for device notification support'));
										}
									} else { // username too long
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' =>'Username must be between 3 and 16 characters'));
									}
								} else { // no first/last name
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Please include your name'));
								}
							} else { // no device sig
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Missing device description'));
							}
						} else { // no device sig
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Missing device signature'));
						}
					} else { // no password
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing SafeText password'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText username'));
				}
			} else if (MS_REQUEST_METHOD === 'DELETE') { // non-POST request
//				$viewObject->setValue('status', 'fail');
//				$viewObject->setValue('data', array('message' => 'Delete requests not yet implemented'));
				
				// **** DELETE USER **** //
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->purge()) {
				
							$viewObject->setValue('status', 'success');
				
						} else { // error at stored procedure
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'There was a problem trying to clear the database'));
						}
					} else { // no token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				}  else { // unsupported request method
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
				
				
				
			}  else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Contact Action.
	 * 
	 * A POST request updates an existing contact. 
	 * A DELETE request removes this contact record for the user.
	 * Only accessible from the WEB CLIENT.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if ($user->getRelationship('device')->getValue('signature') === 'webclient') {
								if (array_key_exists('contact', $this->params) && $this->params['contact'] > 0) {
							
									// Create a database connection to share
									$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
									if (MS_REQUEST_METHOD === 'POST') { // update contact record								
										// set up variables for db call
										array_key_exists('name', $this->params)? $name = $this->escapeForDb($this->params['name']): $name = 'Unknown';
										array_key_exists('phone', $this->params)? $phone = $this->escapeForDb($this->params['phone']): $phone = '';
										array_key_exists('email', $this->params)? $email = $this->escapeForDb($this->params['email']): $email = '';
										array_key_exists('whitelist', $this->params)? $whitelist = $this->escapeForDb($this->params['whitelist']): $whitelist = '0';
										array_key_exists('blocked', $this->params)? $blocked = $this->escapeForDb($this->params['blocked']): $blocked = '0';
										
										// make update and add to device sync queues via stored procedure	
										$db->CALL("syncContact('" . $user->id . "','" . $this->params['contact'] . "','" . $name . "','" . $email . "','" . $phone . "','" . $whitelist . "','" . $blocked . "')");
										
										// if we're blocking the contact, delete this user as the blockee's contact, if exists
										if ($blocked == '1') $db->CALL("syncBlockContactDelete('" . $this->params['contact'] . "', '" . $user->id . "')");
										
											
										// send feedback to client
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
									} else if (MS_REQUEST_METHOD === 'DELETE') { // remove contact record
									
										// delete contact and add to device sync queues via stored procedure
										$db->CALL("syncContactDelete('" . $user->id . "','" . $this->params['contact'] . "')");
										
										// send feedback to client
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										
									} else { // non-allowed request type
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
									}
								} else { // no contact ID
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Missing contact ID'));
								}
							} else { // not web client
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'This web service is not authorized for that device. Web Client access only.'));
							}
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Message Action.
	 * 
	 * A POST request updates an existing message. Currently the only available update is to change the is_draft attribute from 1 to 0.
	 * A DELETE request deletes this message.
	 * Only accessible from the WEB CLIENT.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function messageAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
					// authenticate token with stored procedure
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							if ($user->getRelationship('device')->getValue('signature') === 'webclient') {
								if (array_key_exists('message', $this->params) && $this->params['message'] > 0) {
							
									// Create a database connection to share
									$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
								
									if (MS_REQUEST_METHOD === 'POST') { // update message record, setting is_draft to '1'								
										
										// load all drafts
										$messagesArray = $db->call("messages('" . $user->getValue('id') . "','drafts','0','999999')");
										$messages = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
										$messages->load($messagesArray);
										
										$this_message = $messages->find('id', $this->params['message']);
										if ($this_message instanceof SafetextMessage && $this_message->isValid()) {
											// sync the update
											$db->call("syncMessage('" . $user->id . "','" . $this->params['message'] . "','" . $this_message->is_important . "','0','0')");
$this->config['log']->write('call: ' . "syncMessage('" . $user->id . "','" . $this->params['message'] . "','" . $this_message->is_important . "','0','0')", 'Web client debug trace');
											
											// send feedback to client
											$viewObject->setValue('status', 'success');
											$viewObject->setValue('token', $user->getRelationship('device')->token);
										} else {
											$viewObject->setValue('status', 'fail');
											$viewObject->setValue('token', $user->getRelationship('device')->token);
											$viewObject->setValue('data', array('message' =>'Unable to locate draft entry'));
											
										}
										
									} else if (MS_REQUEST_METHOD === 'DELETE') { // delete message
									
										// delete message and add to all participants' device sync queues via stored procedure
										$db->CALL("syncMessageDelete('" . $this->params['message'] . "')");
										
										// send feedback to client
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										
									} else { // non-allowed request type
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
									}
								} else { // no contact ID
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Missing message ID'));
								}
							} else { // not web client
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'This web service is not authorized for that device. Web Client access only.'));
							}
						} else { // invalid device
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					} else { // invalid token
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
					}
				} else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Images Action.
	 * 
	 * Images are uploaded to the server and can be queried for their thumb URLs by the mobile app using the Images web service
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Images
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function imagesAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
				// Create a database connection to share
				$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
							
				// authenticate token with stored procedure
				require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
				$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
		
				if ($user instanceof SafetextUser && $user->isValid()) {
			
					if (MS_REQUEST_METHOD === 'GET') {
						/* GET IMAGE DETAILS */
						if (array_key_exists('image', $this->params) && $this->params['image'] !== '') {
							// retrieve details via stored procedure
							$result = current($db->call("getImage('" . $this->params['image'] . "')"));
							
							if ($result['key'] != '') {
								$viewObject->setValue('status', 'success');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array(
									'key' => $result['key'],
									'large' => MS_URL_BASE . '/assets/images/users/' . $result['filename'] . '-l.jpg',
									'medium' => MS_URL_BASE . '/assets/images/users/' . $result['filename'] . '-m.jpg',
									'small' => MS_URL_BASE . '/assets/images/users/' . $result['filename'] . '-s.jpg',
									'deletes_in' => round((strtotime($result['expire_date']) - time())/60)
								));
							
								// log the request
								$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . '), image ' . $this->params['image'], 'Image Details Request');
							} else {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Image not found'));
							}
						} else {
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', array('message' => 'No image specified'));
						}
					} else if (MS_REQUEST_METHOD === 'POST') {
						/* UPLOAD IMAGE */
						$file = current($_FILES);
						if(isset($file) && is_uploaded_file($file['tmp_name'])) {
							
							$status = "";
							$ImageName 		= str_replace(' ','-',strtolower($file['name'])); //get image name
							$ImageSize 		= $file['size']; // get original image size
							$TempSrc	 	= $file['tmp_name']; // Temp name of image file stored in PHP tmp folder
							//$ImageType	 	= $file['type']; //get file type, returns "image/png", image/jpeg, text/plain etc.
							
							$ImageType1 = end(explode(".",$file['name']));
							$ImageType = strtolower($ImageType1);
							
							if($ImageType=='jpeg' || $ImageType=='pjpeg' || $ImageType=='jpg')
							{
								$CreatedImage = imagecreatefromjpeg($file['tmp_name']);
							}
							else if($ImageType=='png')
							{
								$CreatedImage =  @imagecreatefrompng($file['tmp_name']);
								if($CreatedImage==false) {
								    $CreatedImage = true;
									$status = "upload";
								}
							}
							else if($ImageType=='gif')
							{
								$CreatedImage =  imagecreatefromgif($file['tmp_name']);
							}
							else
							{
								$CreatedImage = false;
							}
							
							//Let's check allowed $ImageType, we use PHP SWITCH statement here
							/*switch(strtolower($ImageType))
							{
								case 'image/png':
									//Create a new image from file 
									$CreatedImage =  imagecreatefrompng($file['tmp_name']);
									break;
								case 'image/gif':
									$CreatedImage =  imagecreatefromgif($file['tmp_name']);
									break;			
								case 'image/jpeg':
								case 'image/pjpeg':
									$CreatedImage = imagecreatefromjpeg($file['tmp_name']);
									break;
								default:
									$CreatedImage = false;
							}*/
							
							if ($CreatedImage !== false) {
								//PHP getimagesize() function returns height/width from image file stored in PHP tmp folder.
								//Get first two values from image, width and height. 
								//list assign svalues to $CurWidth,$CurHeight
								list($CurWidth,$CurHeight)=getimagesize($TempSrc);

								//Construct a new unique base filename
								$NewImageName = MD5($ImageName . $user->id . time());
								
								// base filename with prepended path
								$NewImageDest = MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $NewImageName;
								
								
								if($status=="upload") {
									
									$path1 = MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $NewImageName.'-s.jpg';
									$path2 = MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $NewImageName.'-m.jpg';
									$path3 = MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $NewImageName.'-l.jpg';
									
									move_uploaded_file($file['tmp_name'],$path1);
									copy($path1,$path2);
									copy($path1,$path3);
									
									$result = current($db->call("putImage('" . $user->id . "','$NewImageName')"));
									if ($result['key'] != '') {
						
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array(
											'key' => $result['key'],
											'large' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-l.jpg',
											'medium' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-m.jpg',
											'small' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-s.jpg',
											'deletes_in' => round((strtotime($result['expire_date']) - time())/60)
										));
										
										// log the request
										$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . ')', 'Image Upload');						
									
									} else {
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('message' => $result['msg']));
									}
									
								}
								else {
								//Resize image to Specified Size by calling resizeImage function.
								$large_file = max($CurWidth,$CurHeight);
								
								if($large_file < 375) {
									$large = 600;
								}
								else {
									$large = $large_file;
								}
								
								if($this->_resizeImage($CurWidth,$CurHeight,$large,$NewImageDest . '-l.jpg',$CreatedImage,$this->config['imagesQuality'])) {
									$this->_resizeImage($CurWidth,$CurHeight,375,$NewImageDest . '-m.jpg',$CreatedImage,$this->config['imagesQuality']);
									$this->_resizeImage($CurWidth,$CurHeight,150,$NewImageDest . '-s.jpg',$CreatedImage,$this->config['imagesQuality']);
							
									// store database reference and generate an image key
									$result = current($db->call("putImage('" . $user->id . "','$NewImageName')"));
									
									if ($result['key'] != '') {
						
										$viewObject->setValue('status', 'success');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array(
											'key' => $result['key'],
											'large' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-l.jpg',
											'medium' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-m.jpg',
											'small' => MS_URL_BASE . '/assets/images/users/' . $NewImageName . '-s.jpg',
											'deletes_in' => round((strtotime($result['expire_date']) - time())/60)
										));
										
										// log the request
										$this->config['log']->write('User: ' . $user->id . ' (' . $user->username . ')', 'Image Upload');						
									
									} else {
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('token', $user->getRelationship('device')->token);
										$viewObject->setValue('data', array('message' => $result['msg']));
									}
								} else {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('token', $user->getRelationship('device')->token);
									$viewObject->setValue('data', array('message' => 'Unable to resize image'));
								}
							}
							/* end */	
							} else {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('token', $user->getRelationship('device')->token);
								$viewObject->setValue('data', array('message' => 'Unsupported filetype'));
							}
						} else {
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('token', $user->getRelationship('device')->token);
							$viewObject->setValue('data', array('message' => 'No image file'));
						}
					} else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
					}
				} else { // invalid token
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'That auth token is not valid'));
				}
			} else { // no token
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => 'Missing auth token'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Send Reminder Email.
	 * 
	 * A user has provided their email address and requests an email be sent to them with their username and a link
	 * to reset their password.
	 *
	 * @link https://github.com/deztopia/safetext
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function sendreminderemailAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('email', $this->params) && $this->params['email'] !== '') {
					
					// Create a database connection to share
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);

					// generate verification code
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$codeDetails = SafetextUser::sendReminderEmail($this->params['email'], $db, $this->config);
					
					if ($codeDetails['code'] != '') {
						$viewObject->setValue('status', 'success');
						
						// log the request
						$this->config['log']->write('User: ' . $codeDetails['id'] . ', Verification Code: ' . $codeDetails['token'], 'Login Help Email Request');
					} else { // unsuccessful code generation
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => $codeDetails['msg']));
					}
				} else { // no device description
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing email address'));
				}

			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for auth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	/**
	 * Reset Password.
	 * 
	 * A user has requested to reset their password. Validate the passed verification code, update password, and clear all existing contacts and messages.
	 *
	 * @link https://github.com/deztopia/safetext
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function resetpassAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		// ensure we're using https
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				if (array_key_exists('HTTP_X_SAFETEXT_CODE', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_CODE'] !== '') {
					if (array_key_exists('pass', $this->params) && $this->params['pass'] !== '') {
						
						// Create a database connection to share
						$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
	
						// generate verification code
						require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
						$resultDetails = SafetextUser::resetPass(md5($this->params['pass']), $_SERVER['HTTP_X_SAFETEXT_CODE'], $db, $this->config);
						
						if ($resultDetails[id] > 0) {
							$viewObject->setValue('status', 'success');
							
							// log the request
							$this->config['log']->write('User: ' . $resultDetails['id'] . ', Verification Code: ' . $_SERVER['HTTP_X_SAFETEXT_CODE'], 'Reset Password Request');
						} else { // unsuccessful reset
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => $resultDetails['msg']));
						}
					} else { // no device description
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing password'));
					}
				
				} else { // no verification code
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing password reset verification code'));
				}
			} else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for auth'));
			}
		} else { // insecure
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
	}
	
	
	
	
	
	
	
	
	/**
	 * Escape For DB.
	 * Escapes a string to be sent to a DB stored procedure.
	 *
	 * @param String $val
	 * @return String
	 */
	protected function escapeForDb($val) {
		return str_replace("'", "\'", $val);
	}
	
	/**
	 * Resize Image.
	 * Resizes an image for local storage.
	 *
	 * @param String $CurWidth
	 * @param String $CurHeight
	 * @param String $MaxSize
	 * @param String $DestFolder
	 * @param String $SrcImage
	 * @param String $Quality
	 * @return Boolean
	 */
	// This function will proportionally resize image 
	protected function _resizeImage($CurWidth,$CurHeight,$MaxSize,$DestFolder,$SrcImage,$Quality)
	{
		//Check Image size is not 0
		if($CurWidth <= 0 || $CurHeight <= 0) 
		{
			return false;
		}
		
		//Construct a proportional size of new image
		$ImageScale      	= min($MaxSize/$CurWidth, $MaxSize/$CurHeight); 
		$NewWidth  			= ceil($ImageScale*$CurWidth);
		$NewHeight 			= ceil($ImageScale*$CurHeight);
		$NewCanves 			= imagecreatetruecolor($NewWidth, $NewHeight);
		
		// Resize Image
		if(@imagecopyresampled($NewCanves, $SrcImage,0, 0, 0, 0, $NewWidth, $NewHeight, $CurWidth, $CurHeight))
		{
			@imagejpeg($NewCanves,$DestFolder,$Quality);
			
			//Destroy image, frees memory	
			if(is_resource($NewCanves)) {imagedestroy($NewCanves);} 
			return true;
		}
	
	}
	
	
	public function testAction(&$viewObject) {
		$viewObject->setResponseType('json');
		
		$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
		//$result = $db->call("syncPull('3','8');");
		$result = $db->call("getGroupusername('97','100');");
		print_r($result);
		/*$test = '';
		if (is_array($result)) {
			foreach ($result as $row) {
				foreach ($row as $key=>$val) {
					$test .= "$key=$val,";
				} 
			}
		} else{
			$test = $result;
		}
		
		$viewObject->setValue('Test', $test);*/

		echo date_default_timezone_get().date('T');
		
	}
	
	public function addgroupAction(&$viewObject) {
	
	 		$viewObject->setResponseType('json');
	 	
			if (MS_PROTOCOL === 'https') {
		
				if (MS_REQUEST_METHOD === 'POST') {
				
					if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
					
						$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					
						// authenticate token with stored procedure
						require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
						$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
			
						if ($user instanceof SafetextUser && $user->isValid()) {
						
						array_key_exists('participants_id', $this->params)? $participants_id= $this->params['participants_id']: $participants_id= '';
						array_key_exists('group_name', $this->params)? $group_name= $this->params['group_name']: $group_name= '';
						
						$sql_insert = "INSERT INTO groups(group_name,participants_id,token_id) VALUES('$group_name','$participants_id','".$_SERVER['HTTP_X_SAFETEXT_TOKEN']."')";
						$result = current($db->query($sql_insert));
						$message = "success";
				
						$sql_group_id = "SELECT id FROM groups ORDER BY id DESC LIMIT 1";
						$f_group_id = current($db->query($sql_group_id));
						
						/*$sql_username = "SELECT `username` FROM `users` WHERE `id` IN (".$participants_id.")";
						$f_username = current($db->query($sql_username));
						
						foreach($f_username as $this_username) {
							$arr_username[] = $this_username['username'];
						}
						$username = implode(",",$arr_username);*/
						
						$participants = explode(",",$participants_id);
						
						$key = array_search($user->id, $participants);
						unset($participants[$key]);
							
						$participants = array_values($participants);
						$count_p = count($participants);
						
						
						for($i=0;$i<$count_p;$i++) {
						
							$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
							if($f_username['name']!="")
							$username .= $f_username['name'];
							else
							$username .= $f_username['username'];
							if($i<($count_p-1))
							$username .= ",";
						}

                                                /* getting all username */
						
						$old_participants = explode(",",$participants_id);
						//print_r($old_participants);
						
						$count = count($old_participants);
						
						for($j=0;$j<$count;$j++) {
							
							$username1 = array();
							$o_participant = array();
							
							//$o_participant1 = $old_participants;
							
							$o_participant = array_diff($old_participants, array($old_participants[$j]));
							
							//print_r($o_participant);
							
							for($k=0;$k<$count;$k++) {
								//echo "getGroupUsername('".$o_participant[$k]."','".$old_participants[$j]."')";
								$f_username = current($db->call("getGroupUsername('".$o_participant[$k]."','".$old_participants[$j]."')"));
								if($f_username['name']!="")
								$username1[] = $f_username['name'];
								else if($f_username['username']!="")
								$username1[] = $f_username['username'];
							}
							
							$username2 = implode(",",$username1);
							
							//echo $old_participants[$j]."--".$username2;
							
							//send to sync 
							$result = current($db->call("syncAddGroup('".$user->id."','".$group_name."','".$f_group_id[0]['id']."','".$participants_id."','".$username2."','".$old_participants[$j]."')"));
							
						}
						
						/* end */

						
						
						//$result = current($db->call("syncAddGroup('".$user->id."','".$group_name."','".$f_group_id[0]['id']."','".$participants_id."','".$username."')"));						
			
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_name' => $group_name, 'group_id' => $f_group_id[0]['id'], 'participants_id' => $participants_id, 'participants_name' => $username, 'token' =>$user->getRelationship('device')->token));
						
						
						}
						else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
						}
					}
					else { // no username
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
					}
			}
			else { // non-POST request
				$viewObject->setValue('status', 'fail');
				$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		}
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));	
		}				
	 }
	 
	 public function deletegroupAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
		
			if (MS_REQUEST_METHOD === 'POST') {
				
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
			
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					// ensure we're using http
			
					array_key_exists('group_id', $this->params)? $group_id= $this->params['group_id']: $group_id= '';
					
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
			
					if ($user instanceof SafetextUser && $user->isValid()) {
					
					$sql_participants = "SELECT participants_id FROM groups WHERE id = '$group_id'";
					$participants = current($db->query($sql_participants));
					
					$sql_get_message = "SELECT message_id FROM group_message WHERE group_id = '$group_id'";
					$f_get_message = current($db->query($sql_get_message));
					
					foreach($f_get_message as $this_message) {
							$sql_del_sync = "DELETE FROM sync_queue WHERE tablename='messages' AND pk='".$this_message['message_id']."'";
							$del_result = current($db->query($sql_del_sync));
					}
					
					
					
					$sql_del_msg = "DELETE FROM group_message WHERE group_id = '$group_id'";
					$q_del_msg = current($db->query($sql_del_msg));
			
					$sql_del = "DELETE FROM groups WHERE id = '$group_id'";
					$result = current($db->query($sql_del));
					
										
					
					$result2 = current($db->call("syncGroupDelete('".$group_id."','".$participants[0]['participants_id']."')"));
					
					$message = "success";
				
					$viewObject->setValue('status', $message);
					$viewObject->setValue('data', array('group_id' => $group_id, 'token' =>$user->getRelationship('device')->token));
					}
					else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
					}
				}	
				else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		}	
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}		
	 }
	 
	 public function addcontactAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
			
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					// ensure we're using http
			
					array_key_exists('group_id', $this->params)? $group_id= $this->params['group_id']: $group_id= '';
					
					array_key_exists('contact_id', $this->params)? $contact_id= $this->params['contact_id']: $contact_id= '';
					
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						
						$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
						$result_contact = current($db->query($sql_contact));
						
						if($contact_id!='') {
						$new_participants_id = $result_contact[0]['participants_id'].','.$contact_id;
						}
						else {
						$new_participants_id = $result_contact[0]['participants_id'];
						}
						
						/*$sql_username = "SELECT `username` FROM `users` WHERE `id` IN (".$new_participants_id.")";
						$f_username = current($db->query($sql_username));
						
						foreach($f_username as $this_username) {
							$arr_username[] = $this_username['username'];
						}
						$username = implode(",",$arr_username);*/
						
						$participants = explode(",",$new_participants_id);
						$count_p = count($participants);
						for($i=0;$i<$count_p;$i++) {
						
							$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
							if($f_username['name']!="")
							$username .= $f_username['name'];
							else
							$username .= $f_username['username'];
							if($i<($count_p-1))
							$username .= ",";
						}
						
						$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
						$result_update = current($db->query($sql_update_participant));
						
						$result = current($db->call("syncGroupContactAdd('".$group_id."','".$new_participants_id."','".$username."')"));
						
						$message = "success";
						
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_id' => $group_id, 'participants_id' => $new_participants_id, 'participants_name' => $username, 'token' =>$user->getRelationship('device')->token));
					}
					else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
					}	
				}
				else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}	
		}
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}		
				
	 }
	 
	 public function removecontactAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
			
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					// ensure we're using http
			
					array_key_exists('group_id', $this->params)? $group_id= $this->params['group_id']: $group_id= '';
					
					array_key_exists('contact_id', $this->params)? $contact_id= $this->params['contact_id']: $contact_id= '';
					
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
						
						$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
						$result_contact = current($db->query($sql_contact));
						
						if($contact_id!='') {
						$participants_id = explode(",",$result_contact[0]['participants_id']);
						//print_r($participants_id);
						
						$arr_contact = explode(",",$contact_id);
						$arr = array_diff($participants_id, $arr_contact);
						//print_r($arr);
						$new_participants_id = implode(",",$arr);
						}
						else {
						$new_participants_id = $result_contact[0]['participants_id'];
						}
																		
						/*$sql_username = "SELECT `username` FROM `users` WHERE `id` IN (".$new_participants_id.")";
						$f_username = current($db->query($sql_username));
						
						foreach($f_username as $this_username) {
							$arr_username[] = $this_username['username'];
						}
						$username = implode(",",$arr_username);*/
						
						$participants = explode(",",$new_participants_id);
						$count_p = count($participants);
						for($i=0;$i<$count_p;$i++) {
						
							$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
							if($f_username['name']!="")
							$username .= $f_username['name'];
							else
							$username .= $f_username['username'];
							if($i<($count_p-1))
							$username .= ",";
						}
						
						$result = current($db->call("syncGroupContactDelete('".$group_id."','".$result_contact[0]['participants_id']."','".$new_participants_id."','".$username."')"));
						
						$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
						$result_update = current($db->query($sql_update_participant));
						$message = "success";
						
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_id' => $group_id, 'participants_id' => $new_participants_id, 'participants_name' => $username, 'token' =>$user->getRelationship('device')->token));
					}
					else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
					}
				}
				else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}	
		}
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}	
					
	 }
	 
	 /******** Edit Group **********/
	 
	 public function editgroupAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				
				if (array_key_exists('HTTP_X_SAFETEXT_TOKEN', $_SERVER) && $_SERVER['HTTP_X_SAFETEXT_TOKEN'] !== '') {
			
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					// ensure we're using http
			
					array_key_exists('group_id', $this->params)? $group_id= $this->params['group_id']: $group_id= '';
					
					array_key_exists('contact_id', $this->params)? $contact_id= $this->params['contact_id']: $contact_id= '';
					
					array_key_exists('action', $this->params)? $action= $this->params['action']: $action= '';
					
					array_key_exists('new_group_name', $this->params)? $new_group_name= $this->params['new_group_name']: $new_group_name= '';
					
					require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
					$user = SafetextUser::tokenToUser($_SERVER['HTTP_X_SAFETEXT_TOKEN'], $db, $this->config);
					
					if ($user instanceof SafetextUser && $user->isValid()) {
					
						$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
						$result_contact = current($db->query($sql_contact));
						
						$new_participants_id = $result_contact[0]['participants_id'];
						
						$participants = explode(",",$new_participants_id);
						
						
						$key = array_search($user->id, $participants);
						unset($participants[$key]);
							
						$participants = array_values($participants);
						$count_p = count($participants);
						
						for($i=0;$i<$count_p;$i++) {
							
								$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
								if($f_username['name']!="")
								$username .= $f_username['name'];
								else
								$username .= $f_username['username'];
								if($i<($count_p-1))
								$username .= ",";
							}
					
						/* Remove Action */
						if($action=='remove') {
						
							$username = "";
						
							$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
							$result_contact = current($db->query($sql_contact));
							
							if($contact_id!='') {
							$participants_id = explode(",",$result_contact[0]['participants_id']);
							//print_r($participants_id);
							
							$arr_contact = explode(",",$contact_id);
							$arr = array_diff($participants_id, $arr_contact);
							//print_r($arr);
							$new_participants_id = implode(",",$arr);
							}
							else {
							$new_participants_id = $result_contact[0]['participants_id'];
							}
							
							$participants = explode(",",$new_participants_id);
							
							
							$key = array_search($user->id, $participants);
							unset($participants[$key]);
							
							
							
							$participants = array_values($participants);
							$count_p = count($participants);
							
							//$participants = array_values($result_contact[0]['participants_id']);
							//$count_p = count($participants);
							//print_r($participants);
						
							for($i=0;$i<$count_p;$i++) {
							
								$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
								if($f_username['name']!="")
								$username .= $f_username['name'];
								else
								$username .= $f_username['username'];
								if($i<($count_p-1))
								$username .= ",";
							}
							
							$old_participant = explode(",",$result_contact[0]['participants_id']);
							//print_r($old_participant);
							$count=count($old_participant);
							
							$old_participant1 = array_diff($old_participant, $arr_contact);
							
							for($k=0;$k<$count;$k++) {
							
							$o_participant = array();
							
							$o_participant1 = $old_participant1;
							
							
							$o_participant = array_diff($o_participant1, array($old_participant[$k]));
							
							$count_username = count($old_participant);
							//echo $old_participant[$k];
							//print_r($o_participant1);
							$username1 = array();
							for($j=0;$j<$count_username;$j++) {
							
							//echo "getGroupUsername('".$o_participant[$j]."','".$old_participant[$k]."')";
							$f_username = current($db->call("getGroupUsername('".$o_participant[$j]."','".$old_participant[$k]."')"));
							if($f_username['name']!="")
							$username1[] = $f_username['name'];
							else if($f_username['username']!="")
							$username1[] = $f_username['username'];
							//if($j<($count_username-1) && ($o_participant[$j]!=""))
							//$username1 .= ",";
							
							}
							
							$username1 = implode(",",$username1);
							
							//echo $username1;
							//echo "<br>";

                                                        /* Get Group Name */
							
							$user_group_name = current($db->call("checkGroupname('".$group_id."','".$old_participant[$k]."')"));
							if($user_group_name['group_name']!="") {
								$group_name = $user_group_name['group_name'];
							}
							else {
								$sql_group_name = "SELECT `group_name` FROM groups WHERE `id`='".$group_id."'";
								$f_group_name = current($db->query($sql_group_name));
							
								$group_name = $f_group_name[0]['group_name'];
							}
							
							
							/* End */


							
							$result = current($db->call("syncGroupContactDelete('".$group_name."','".$group_id."','".$old_participant[$k]."','".$new_participants_id."','".$username1."')"));
							
							}
							
							
							
							$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
							$result_update = current($db->query($sql_update_participant));
						
						}
						/* Remove Action End */

						
						/* Add Action */
						if($action=='add') {
						
							$username = "";
							
							$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
							$result_contact = current($db->query($sql_contact));
							
							if($contact_id!='') {
							$new_participants_id = $result_contact[0]['participants_id'].','.$contact_id;
							}
							else {
							$new_participants_id = $result_contact[0]['participants_id'];
							}
							
							$participants = explode(",",$new_participants_id);
						
							$key = array_search($user->id, $participants);
							unset($participants[$key]);
							
							$participants = array_values($participants);
							$count_p = count($participants);
							
							for($i=0;$i<$count_p;$i++) {
							
								$f_username = current($db->call("getGroupUsername('".$participants[$i]."','".$user->id."')"));
								if($f_username['name']!="")
								$username .= $f_username['name'];
								else
								$username .= $f_username['username'];
								if($i<($count_p-1))
								$username .= ",";
							}
							
							/*add sync*/
							
							$new_participants = explode(",",$new_participants_id);
							
							$count=count($new_participants);
							
							//print_r($new_participants);
							
							$n_participant = array();
							
							for($l=0;$l<$count;$l++) {
							
							$n_participant = array_diff($new_participants, array($new_participants[$l]));
							
							//echo $new_participants[$l];
							//print_r($n_participant);
							
							$username1 = array();
							$count_username = count($new_participants);
							
							for($j=0;$j<$count_username;$j++) {
							
							//echo "getGroupUsername('".$n_participant[$j]."','".$new_participants[$l]."')";
							$f_username = current($db->call("getGroupUsername('".$n_participant[$j]."','".$new_participants[$l]."')"));
							if($f_username['name']!="")
							$username1[] = $f_username['name'];
							else if($f_username['username']!="")
							$username1[] = $f_username['username'];
							//if($j<($count_username-1) && ($o_participant[$j]!=""))
							//$username1 .= ",";
							
							}
							
							$username1 = implode(",",$username1);
							
							//echo $username1;
							//echo "<br>";

                                                        /* Get Group Name */
							
							$user_group_name = current($db->call("checkGroupname('".$group_id."','".$new_participants[$l]."')"));
							if($user_group_name['group_name']!="") {
								$group_name = $user_group_name['group_name'];
							}
							else {
								$sql_group_name = "SELECT `group_name` FROM groups WHERE `id`='".$group_id."'";
								$f_group_name = current($db->query($sql_group_name));
							
								$group_name = $f_group_name[0]['group_name'];
							}
							
							/* End */
							
							
							$result = current($db->call("syncGroupContactAdd('".$group_name."','".$group_id."','".$new_participants[$l]."','".$new_participants_id."','".$username1."')"));
							
							
							}
							
							/*end*/
							
							$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
							$result_update = current($db->query($sql_update_participant));
							
							
						
						

						}
						/* Add Action End */

						
						if($new_group_name!="") {
						
							$sql_chk_group_name = "SELECT COUNT(`id`) AS `tot_count` FROM `user_group_name` WHERE `user_id`='".$user->id."' AND `group_id`='".$group_id."'";
							
							$f_chk_group_name = current($db->query($sql_chk_group_name));
							
							if($f_chk_group_name[0]['tot_count'] == 0) {
								
								$sql_insert_group_name = "INSERT INTO `user_group_name`(`user_id`,`group_id`,`group_name`) VALUES('".$user->id."','".$group_id."','".$new_group_name."')";
								
								$insert_group_name = current($db->query($sql_insert_group_name));
								
							}
							else {
								
								$sql_update_group_name = "UPDATE `user_group_name` SET `group_name`='".$new_group_name."' WHERE `user_id`='".$user->id."' AND group_id='".$group_id."'";
								
								$update_group_name = current($db->query($sql_update_group_name));
							}
						}
						else {
							
							$sql_group_name = "SELECT `group_name` FROM groups WHERE `id`='".$group_id."'";
							$f_group_name = current($db->query($sql_group_name));
							
							$new_group_name = $f_group_name[0]['group_name'];
						}
						
						
						$message = "success";
						
						$viewObject->setValue('status', $message);
						$result = current($db->call("syncGroupNameEdit('".$new_group_name."','".$group_id."','".$new_participants_id."','".$username."','".$user->id."','".$user->getRelationship('device')->id."')"));
						$viewObject->setValue('data', array('group_name' => $new_group_name, 'group_id' => $group_id, 'participants_id' => $new_participants_id, 'participants_name' => $username, 'token' =>$user->getRelationship('device')->token));
						
					}
					else {
						$viewObject->setValue('status', 'fail');
						$viewObject->setValue('data', array('message' => 'Problem trying to load device details for that token'));
					}
				}
				else { // no username
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => 'Missing SafeText auth token. Please log in'));
				}
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}	
		}
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}	
					
	 }
	 
	 /******** End **********/
	 
	 public function forgotpasswordAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
		
			if (MS_REQUEST_METHOD === 'POST') {
							
					$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
					// ensure we're using http
			
					array_key_exists('username', $this->params)? $username= $this->params['username']: $username = '';
                                        
                                        array_key_exists('st_user', $this->params)? $username_web= $this->params['st_user']: $username_web = '';
						$sql_pass = "SELECT `pass`,`email` FROM users WHERE username='$username' OR username='$username_web'";
						$f_pass = current($db->query($sql_pass));
						
						if($f_pass[0]['email']=="") {
							$viewObject->setValue('status', 'fail');
							$viewObject->setValue('data', array('message' => 'Email ID does not exist for this Username.'));
						}
						else {
						
							 $result = current($db->call("generateVerificationCode('".$f_pass[0]['email']."')"));
						
							 //require_once "Mail.php";

                                                         $from = "Safe Text Password Reset <password@safe-text.com>";
                                                         //$to = "Brett McReynolds <brett.mcreynolds@safe-text.com>";
                                                         //$to = "Jayanta Saha <jayanta.freelancer@gmail.com>";
                                                         //$to = "ST Contact <contact@safe-text.com>";
                                                        // $to = "Sudeb Mukherjee <sudebmukherjee6@gmail.com>";

                                                         $to = $f_pass[0]['email'];


                                                         $subject = "Reset your Safe-Text Password";
                                                         $body = "You have requested to reset your Safe Text password. Please click here to reset your password.\n\n";
                                                         $body .= "https://client.safe-text.com/auth/resetpass/verification/".$result['code']."\n\n";
                                                         $body .= "The Safe-Text Team";

                                                         $host = "mail.safe-text.com";
                                                         $port = "587";
                                                         $pearusername = "password@safe-text.com";
                                                         $pearpassword = "XxyL82#PQRI";

                                                         $headers = array ('From' => $from,
                                                                           'To' => $to,
                                                                           'Subject' => $subject);
                                                         $smtp = Mail::factory('smtp',
                                                                               array ('host' => $host,
                                                                                      'port' => $port,
                                                                                      'auth' => true,
                                                                                      'username' => $pearusername,
                                                                                      'password' => $pearpassword));
                                                         $mail = $smtp->send($to, $headers, $body);
							 
							$viewObject->setValue('status', 'success');
							$viewObject->setValue('data', array('message' => 'Your password has been sent to your Email.'));
						}
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		}	
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}		
	 }


          /* *************** Screen Capture Notification **********************/
	 
	 public function ScreenCaptureAction(&$viewObject) {
	 
	 	$viewObject->setResponseType('json');
		
		if (MS_PROTOCOL === 'https') {
			if (MS_REQUEST_METHOD === 'POST') {
				array_key_exists('sender_id', $this->params)? $sender_id= $this->params['sender_id']: $sender_id = '';
				array_key_exists('receipient_id', $this->params)? $receipient_id= $this->params['receipient_id']: $receipient_id = '';
				array_key_exists('group_id', $this->params)? $group_id= $this->params['group_id']: $group_id = '';
				
				$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
				
				if($receipient_id=="") {	
					$sql_contact = "SELECT participants_id FROM groups WHERE id='$group_id'";
					$result_contact = current($db->query($sql_contact));
					
					$participants_a = explode(",",$result_contact[0]['participants_id']);
					$count = count($participants_a);
					$key = array_search($sender_id, $participants_a);
					unset($participants_a[$key]);
					//print_r($participants_a);

                                        /*send notification message to group */
					
					$sql_sender_username = "SELECT `username` FROM `users` WHERE `id`='".$sender_id."'";
					$f_sender_username = current($db->query($sql_sender_username));
					
					$content = $f_sender_username[0]['username'].' has captured your screenshot of your group chat';
					$is_important = 0;
					$is_draft = 0;
					$lifetime = 1440;
					$image = '';					
					// execute send via stored procedure
					$cipher = new SafetextCipher($this->config['hashSalt']);
					$result = current($db->call("sendGroupMessage('" . $sender_id . "','" . $f_sender_username[0]['username'] . "','".$result_contact[0]['participants_id']."','" . $group_id . "','" . $this->escapeForDb($cipher->encrypt($content)) . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "','" . $image . "')"));

					/* end */
					
					/*send notification*/
					/*for($i=0;$i<$count;$i++) {
										
					$devicesArray = $db->call("devices('".$participants_a[$i]."')");
					$devices = new SafetextModelCollection('SafetextDevice', $this->config, $db);
					$devices->load($devicesArray);										
												
						foreach ($devices as $this_device) 
						{
							$username = "";
							$f_username = current($db->call("getGroupUsername('".$sender_id."','".$participants_a[$i]."')"));
							if($f_username['name']!="")
							$username = $f_username['name'];
							else if($f_username['username']!="")
							$username = $f_username['username'];*/

                                                         /* Get Group Name */
							
							/*$user_group_name = current($db->call("checkGroupname('".$group_id."','".$participants_a[$i]."')"));
							if($user_group_name['group_name']!="") {
								$group_name = $user_group_name['group_name'];
							}
							else {
								$sql_group_name = "SELECT `group_name` FROM groups WHERE `id`='".$group_id."'";
								$f_group_name = current($db->query($sql_group_name));
							
								$group_name = $f_group_name[0]['group_name'];
							}*/
                                                         
							
							/*$value_unread = current($db->CALL("getgroupisread('" .$participants_a[$i]. "')"));
							$badge = $value_unread['tot_unread'];
							$msg = $username.' has captured a screenshot of your group chat';
							$this_device->sendNotification($msg,$badge);
						}	
										
					}*/
					
					
				}
				
				if($group_id=="") {
					// load all registered devices
					$devicesArray = $db->call("devices('" . $receipient_id . "')");
					$devices = new SafetextModelCollection('SafetextDevice', $this->config, $db);
					$devices->load($devicesArray);
					//foreach ($devices as $this_device) $this_device->sendNotification($user->fullName() . ': ' . $this->params['content']);
					
                                        /* For ScreenCapture Message Notification */
					$f_username = current($db->call("getGroupUsername('".$sender_id."','".$receipient_id."')"));
					if($f_username['name']!="")
					$username = $f_username['name'];
					else if($f_username['username']!="")
					$username = $f_username['username'];
				
					$is_important = 0;
					$is_draft = 0;
					$lifetime = 1440;
					$image = '';
				
					$content = $username.' has captured your screenshot of your chat';
					$cipher = new SafetextCipher($this->config['hashSalt']);
					$result = current($db->call("sendMessage('" . $sender_id . "','" . $receipient_id . "','" . $this->escapeForDb($cipher->encrypt($content)) . "','" . $is_important . "','" . $is_draft . "','" . $lifetime . "','" . $image . "')"));
					/* end */							
					
                                        /*foreach ($devices as $this_device) 
					{
					$username = "";
					$f_username = current($db->call("getGroupUsername('".$sender_id."','".$receipient_id."')"));
					if($f_username['name']!="")
					$username = $f_username['name'];
					else if($f_username['username']!="")
					$username = $f_username['username'];*/
					//$val_is_read = current($db->CALL("getisread('" .current($this->params['recipients']). "')"));
					/*$value_unread = current($db->CALL("getgroupisread('" .$receipient_id. "')"));
											
					$badge = $value_unread['tot_unread'];
					
					$msg = $username.' has captured a screenshot of your chat';
												
					$this_device->sendNotification($msg,$badge,$receipient_id);
					}*/
				}
				
				$viewObject->setValue('status', 'Success');
				$viewObject->setValue('data', array('message' => 'Notification send for capturing screen.'));
				
			}
			else { // non-POST request
					$viewObject->setValue('status', 'fail');
					$viewObject->setValue('data', array('message' => MS_REQUEST_METHOD . ' requests are not supported for this web service'));
			}
		}
		else {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-HTTPS) access denied'));
		}
			
	 }
	 
	 
}