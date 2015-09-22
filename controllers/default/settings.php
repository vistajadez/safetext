<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Web Client Controller.
 *
 *
 */
class SettingsController extends SafetextClientController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will display settings summary/edit form.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			//count unread messages
			$sql_unread = "SELECT count(is_read) as unread_msg FROM messages,participants WHERE participants.contact_id='". $this->user->getValue('id')."' AND participants.is_sender=0 AND messages.is_read=0 AND participants.message_id=messages.id";
			$unread = current($this->db->query($sql_unread));
			
			if($unread[0]['unread_msg']>0) {
			$unread_message = $unread[0]['unread_msg'];
			}
			else {
			$unread_message = 0;
			}
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			
			//load notes
			$notes = current($this->db->query("SELECT notes,expiretime FROM users WHERE id='" . $this->user->getValue('id') . "'"));
			
			//load bubble color
			$bubble = current($this->db->query("SELECT color FROM bubble_color WHERE user_id='" . $this->user->getValue('id') . "'"));
			
			// load all registered devices
			$devicesArray = $this->db->call("devices('" . $this->user->getValue('id') . "')");
			$devices = new SafetextModelCollection('SafetextDevice', $this->config, $this->db);
			$devices->load($devicesArray);
			
			// load membership levels
			$subscriptionLevels = $this->db->call("subscriptionLevels()");
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);

			//title
			$viewObject->setTitle('Settings');
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('devices', $devices);
			$viewObject->setValue('lifetime', $notes[0]['expiretime']);
			$viewObject->setValue('color', $bubble[0]['color']);
			$viewObject->setValue('notes', $notes[0]['notes']);
			$viewObject->setValue('subscription_levels', $subscriptionLevels);
		}
	 }
	 
	 
	/**
	 * Subscribe Action.
	 * 
	 * Provides a form for user to upgrade membership
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function subscribeAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load all registered devices
			$devicesArray = $this->db->call("devices('" . $this->user->getValue('id') . "')");
			$devices = new SafetextModelCollection('SafetextDevice', $this->config, $this->db);
			$devices->load($devicesArray);
			
			// load membership levels
			$subscriptionLevels = $this->db->call("subscriptionLevels()");

			//title
			$viewObject->setTitle('Subscribe');
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('devices', $devices);
			$viewObject->setValue('subscription_levels', $subscriptionLevels);
		}
	 }
	 
	 
	/**
	 * Payment Card Details Action (JSON).
	 * 
	 * Pulls a user's payment card details, if any is on file. Endpoint for mobile devices.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Payments
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function paymentcardAction(&$viewObject) {
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
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							// obtain a PayPal authorization token
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/oauth2/token');
							curl_setopt($ch, CURLOPT_HEADER, false);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
							curl_setopt($ch, CURLOPT_USERPWD, $this->config['paypal']['clientid'] . ':' . $this->config['paypal']['secret']);
							curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
							
							$result = curl_exec($ch);
							
							// process response
							$response = json_decode($result, true);						
							if (!is_array($response) || !array_key_exists('access_token', $response) || $response['access_token'] === '') {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Merchant service refused to authorize'));
								return; // terminate the controller action
							}
							
							$authtoken = $response['access_token'];
							curl_close($ch);
								
							// check for existing payment token
							$paymentToken = $user->payment_token;
							
							if ($paymentToken !== '') {
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/vault/credit-card/' . $paymentToken);
								curl_setopt($ch, CURLOPT_HEADER, false);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
								curl_setopt($ch, CURLOPT_HTTPGET, true);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
								curl_setopt($ch, CURLOPT_HTTPHEADER, array(
									'Authorization: Bearer ' . $authtoken,
									'Accept: application/json',
									'Content-Type: application/json'
								));
								$result = curl_exec($ch);
							
								// process response
								$response = json_decode($result, true);						
								if (!is_array($response) || !array_key_exists('state', $response) || $response['state'] !== 'ok') {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Unable to lookup your card details. You may want to try updating your payment card'));
									return; // terminate the controller action
								}
								
								$viewObject->setValue('status', 'success');
								$viewObject->setValue('data', array('lastfour' => substr($response['number'], -4), 'type' => $response['type'], 'expire_month' => $response['expire_month'], 'expire_year' => $response['expire_year']));
								return;
								
							} else {
								$viewObject->setValue('status', 'success');
								$viewObject->setValue('data', array());
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
	 * Process Payment Action (JSON).
	 * 
	 * Sends a request to process a subscription payment.
	 *
	 * @link https://github.com/deztopia/safetext/wiki/Payments
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function processpaymentAction(&$viewObject) {
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
						$viewObject->setValue('token', $user->getRelationship('device')->token);
						if ($user->getRelationship('device') instanceof SafetextDevice && $user->getRelationship('device')->isValid()) {
							// obtain a PayPal authorization token
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/oauth2/token');
							curl_setopt($ch, CURLOPT_HEADER, false);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
							curl_setopt($ch, CURLOPT_USERPWD, $this->config['paypal']['clientid'] . ':' . $this->config['paypal']['secret']);
							curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
							
							$result = curl_exec($ch);
							
							// process response
							$response = json_decode($result, true);						
							if (!is_array($response) || !array_key_exists('access_token', $response) || $response['access_token'] === '') {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Merchant service refused to authorize'));
								return; // terminate the controller action
							}
							
							$authtoken = $response['access_token'];
							curl_close($ch);
								
							// check for existing payment token
							$paymentToken = $user->payment_token;
							
							if ($paymentToken === '') {
								// no credit card on file, so we should have been passed a new one to add
								if (array_key_exists('cc_number', $this->params) && $this->params['cc_number'] != '') {
									if (array_key_exists('cc_cvv2', $this->params) && $this->params['cc_cvv2'] != '') {
										if (array_key_exists('name', $this->params) && $this->params['name'] != '') {
											if (array_key_exists('cc_type', $this->params) && $this->params['cc_type'] != '') {
												if (array_key_exists('cc_exp_month', $this->params) && $this->params['cc_exp_month'] != '') {
													if (array_key_exists('cc_exp_year', $this->params) && $this->params['cc_exp_year'] != '') {
											
														// store card in PayPal Vault, receive payment token
														$name = explode(' ', $this->params['name']);
														$data = array(
															'payer_id' => $user->id,
															'type' => strtolower($this->params['cc_type']),
															'number' => $this->params['cc_number'],
															'expire_month' => $this->params['cc_exp_month'],
															'expire_year' => $this->params['cc_exp_year'],
															'cvv2' => $this->params['cc_cvv2'],
															'first_name' => $name[0],
															'last_name' => $name[1]
														);
														$ch = curl_init();
														curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/vault/credit-card');
														curl_setopt($ch, CURLOPT_HEADER, false);
														curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
														curl_setopt($ch, CURLOPT_POST, true);
														curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
														curl_setopt($ch, CURLOPT_HTTPHEADER, array(
															'Authorization: Bearer ' . $authtoken,
															'Accept: application/json',
															'Content-Type: application/json'
														));
														curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
														$result = curl_exec($ch);
														
														// process response
														$response = json_decode($result, true);						
														if (!is_array($response) || !array_key_exists('id', $response) || $response['id'] === '') {
															$viewObject->setValue('status', 'fail');
															$viewObject->setValue('data', array('message' => 'Card declined'));
															return; // terminate the controller action
														}
														
														$paymentToken = $response['id'];
														curl_close($ch);
														
														// save some values for later calls
														$lastfour = substr($this->params['cc_number'], -4);
														$type = strtolower($this->params['cc_type']);
														$expire_month = $this->params['cc_exp_month'];
														$expire_year = $this->params['cc_exp_year'];
											
											
													} else {
														$viewObject->setValue('status', 'fail');
														$viewObject->setValue('data', array('message' => 'You need to provide the card expiration year'));
														return;
													}
												} else {
													$viewObject->setValue('status', 'fail');
													$viewObject->setValue('data', array('message' => 'You need to provide the card expiration month'));
													return;
												}
											} else {
												$viewObject->setValue('status', 'fail');
												$viewObject->setValue('data', array('message' => 'You need to provide the card type'));
												return;
											}
										} else {
											$viewObject->setValue('status', 'fail');
											$viewObject->setValue('data', array('message' => 'You need to provide the name on your payment card'));
											return;
										}
									} else {
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' => 'You need to provide the CVV2 card code for your payment card'));
										return;
									}
								} else {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'You need to provide a credit card number for payment'));
									return;
								}
							} // end no existing credit card
							
							
							// if we're not saving the card now, load card data from secure PayPal vault
							if (!isset($lastfour) || $lastfour === '') {
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/vault/credit-card/' . $paymentToken);
								curl_setopt($ch, CURLOPT_HEADER, false);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
								curl_setopt($ch, CURLOPT_HTTPGET, true);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
								curl_setopt($ch, CURLOPT_HTTPHEADER, array(
									'Authorization: Bearer ' . $authtoken,
									'Accept: application/json',
									'Content-Type: application/json'
								));
								$result = curl_exec($ch);
							
								// process response
								$response = json_decode($result, true);						
								if (!is_array($response) || !array_key_exists('state', $response) || $response['state'] !== 'ok') {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Unable to lookup your card details. You may want to try updating your payment card'));
									return; // terminate the controller action
								}
								
								// save some values for later calls
								$lastfour = substr($response['number'], -4);
								$type = $response['type'];
								$expire_month = $response['expire_month'];
								$expire_year = $response['expire_year'];
							}
							
							// determine selected subscription options details
							$subscriptionLevels = $db->call("subscriptionLevels()");
							$selectedSubscriptionLevel = str_replace('-r', '', $this->params['subscriptionlevel']);
							if (strpos($this->params['subscriptionlevel'], '-r') > 0) $recurringSubscription = '1';
								else $recurringSubscription = '0';
							foreach ($subscriptionLevels as $this_subscriptionLevel) {
								if ($this_subscriptionLevel['id'] === $selectedSubscriptionLevel) $selectedSubscription = $this_subscriptionLevel;
							}
						
							if (isset($selectedSubscription)) {
								// make initial payment
								$data = array(
									'intent' => 'sale',
									'payer' => array(
										'payment_method' => 'credit_card',
										'funding_instruments' => array(
											array(
												'credit_card_token' => array(
													'credit_card_id' => $paymentToken,
													'payer_id' => $user->id,
													'last4' => $lastfour,
													'type' => $type,
													'expire_month' => $expire_month,
													'expire_year' => $expire_year
												)
											)
										)
									),
									'transactions' => array (
										array(
											'amount' => array(
												'total' => $selectedSubscription['cpm'],
												'currency' => 'USD'
											),
											'description' => 'Safe-Text Membership'
										)
									)
								);
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/payments/payment');
								curl_setopt($ch, CURLOPT_HEADER, false);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
								curl_setopt($ch, CURLOPT_POST, true);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
								curl_setopt($ch, CURLOPT_HTTPHEADER, array(
									'Authorization: Bearer ' . $authtoken,
									'Accept: application/json',
									'Content-Type: application/json'
								));
								curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
								$result = curl_exec($ch);
								
								// process response
								$response = json_decode($result, true);						
								if (!is_array($response) || !array_key_exists('state', $response) || $response['state'] !== 'approved') {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'Transaction was declined'));
									return; // terminate the controller action
								}
								
								// store payment details
								$db->call("putPayment('" . $response['id'] . "','" . $user->id . "','" . $selectedSubscription['cpm'] . "','" . $response['state'] . "')");	
								
								// determine new expiration date
								if ($user->subscription_expires === '0000-00-00')
									$expireDate = new DateTime();
								else 
									$expireDate = new DateTime($user->subscription_expires);
								
								$expireDate->add(new DateInterval('P' . ($selectedSubscription['months'] * 30) . 'D'));
								
								// save subscription status and payment token for user
								$db->call("updateSubscription('" . $user->id . "','" . $selectedSubscription['id'] . "','" . $paymentToken . "','" . $expireDate->format('Y-m-d') . "', '" . $recurringSubscription . "')");
							
								
								$viewObject->setValue('status', 'success');
					
							} else { // unable to find selected subscription
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Problem trying to load the details for your selected subscription option. Please try again or contact us for assistance'));
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
	 * Payments.
	 * 
	 * Displays a user's history of payments.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function paymentsAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			//count unread messages
			$sql_unread = "SELECT count(is_read) as unread_msg FROM messages,participants WHERE participants.contact_id='". $this->user->getValue('id')."' AND participants.is_sender=0 AND messages.is_read=0 AND participants.message_id=messages.id";
			$unread = current($this->db->query($sql_unread));
			
			if($unread[0]['unread_msg']>0) {
			$unread_message = $unread[0]['unread_msg'];
			}
			else {
			$unread_message = 0;
			}
			
			
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			// load all registered devices
			$payments = $this->db->call("payments('" . $this->user->getValue('id') . "')");

			//title
			$viewObject->setTitle('Payment History');
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('payments', $payments);
		}
	 }
	 
	 
	/**
	 * Card.
	 * 
	 * Displays a form to set the payment card details.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function cardAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {

			//title
			$viewObject->setTitle('Update Card');
			
		}
	 }
	 
	 
	/**
	 * Update Card Action (JSON).
	 * 
	 * Sends a request to store a user's card in the PayPal vault and associate the new payment token with the user's account.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function updatecardAction(&$viewObject) {
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
							// obtain a PayPal authorization token
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/oauth2/token');
							curl_setopt($ch, CURLOPT_HEADER, false);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
							curl_setopt($ch, CURLOPT_USERPWD, $this->config['paypal']['clientid'] . ':' . $this->config['paypal']['secret']);
							curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
							
							$result = curl_exec($ch);
							
							// process response
							$response = json_decode($result, true);						
							if (!is_array($response) || !array_key_exists('access_token', $response) || $response['access_token'] === '') {
								$viewObject->setValue('status', 'fail');
								$viewObject->setValue('data', array('message' => 'Merchant service refused to authorize'));
								return; // terminate the controller action
							}
							
							$authtoken = $response['access_token'];
							curl_close($ch);
								
								if (array_key_exists('cc_number', $this->params) && $this->params['cc_number'] != '') {
									if (array_key_exists('cc_cvv2', $this->params) && $this->params['cc_cvv2'] != '') {
										if (array_key_exists('name', $this->params) && $this->params['name'] != '') {
											// store card in PayPal Vault, receive payment token
											$name = explode(' ', $this->params['name']);
											$data = array(
												'payer_id' => $user->id,
												'type' => strtolower($this->params['cc_type']),
												'number' => $this->params['cc_number'],
												'expire_month' => $this->params['cc_exp_month'],
												'expire_year' => $this->params['cc_exp_year'],
												'cvv2' => $this->params['cc_cvv2'],
												'first_name' => $name[0],
												'last_name' => $name[1]
											);
											$ch = curl_init();
											curl_setopt($ch, CURLOPT_URL, $this->config['paypal']['endpoint'] . '/v1/vault/credit-card');
											curl_setopt($ch, CURLOPT_HEADER, false);
											curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
											curl_setopt($ch, CURLOPT_POST, true);
											curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
											curl_setopt($ch, CURLOPT_HTTPHEADER, array(
												'Authorization: Bearer ' . $authtoken,
												'Accept: application/json',
												'Content-Type: application/json'
											));
											curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
											$result = curl_exec($ch);
											
											// process response
											$response = json_decode($result, true);						
											if (!is_array($response) || !array_key_exists('id', $response) || $response['id'] === '') {
												$viewObject->setValue('status', 'fail');
												$viewObject->setValue('data', array('message' => 'Card declined'));
												return; // terminate the controller action
											}
											
											$paymentToken = $response['id'];
											curl_close($ch);
											
											// save subscription status and payment token for user
											$db->call("updateSubscription('" . $user->id . "','" . $user->subscription_level . "','" . $paymentToken . "','" . $user->subscription_expires . "', '" . $user->subscription_recurs . "')");
										
											$viewObject->setValue('status', 'success');
								
											
										} else {
											$viewObject->setValue('status', 'fail');
											$viewObject->setValue('data', array('message' => 'You need to provide the name on your payment card'));
											return;
										}
									} else {
										$viewObject->setValue('status', 'fail');
										$viewObject->setValue('data', array('message' => 'You need to provide the CVV2 card code for your payment card'));
										return;
									}
								} else {
									$viewObject->setValue('status', 'fail');
									$viewObject->setValue('data', array('message' => 'You need to provide a credit card number for payment'));
									return;
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
	 * Cancel Recurring Action.
	 * 
	 * Cancels a user's recurring subscription.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function cancelrecurringAction(&$viewObject) {
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
			
					if (MS_REQUEST_METHOD === 'POST') {

						// save subscription status and payment token for user
						$db->call("updateSubscription('" . $user->id . "','" . $user->subscription_level . "','" . $user->payment_token . "','" . $user->subscription_expires . "', '0')");
						
						$viewObject->setValue('status', 'success');
						
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
	
	
	 
	 
	
	
}