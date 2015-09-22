<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );
/**
 * Controller for Webservices for Group Messages.
 *
 *
 * This is the controller for the SafeText Webservices for Group Messages v 1.
 *
 */
class ApigroupController extends MsController {
	
	public function defaultAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
	 	
	 	// ensure we're using http
		if (MS_PROTOCOL !== 'https') {
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'Insecure (non-https) access denied'));
		} else {	 
			$viewObject->setValue('status', 'fail');
			$viewObject->setValue('data', array('message' => 'No action specified'));
		}
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
						
						$sql_insert = "INSERT INTO groups(participants_id,token_id) VALUES('$participants_id','".$_SERVER['HTTP_X_SAFETEXT_TOKEN']."')";
						$result = current($db->query($sql_insert));
						$message = "success";
				
						$sql_group_id = "SELECT id FROM groups ORDER BY id DESC LIMIT 1";
						$f_group_id = current($db->query($sql_group_id));
			
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_id' => $f_group_id[0]['id'], 'participants_id' => $participants_id, 'token' =>$user->getRelationship('device')->token));
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
			
					$sql_del = "DELETE FROM groups WHERE id = '$group_id'";
					$result = current($db->query($sql_del));
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
						
						$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
						$result_update = current($db->query($sql_update_participant));
						$message = "success";
						
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_id' => $group_id, participants_id => $new_participants_id, 'token' =>$user->getRelationship('device')->token));
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
						
						$sql_update_participant = "UPDATE groups SET participants_id = '$new_participants_id' WHERE id='$group_id'";
						$result_update = current($db->query($sql_update_participant));
						$message = "success";
						
						$viewObject->setValue('status', $message);
						$viewObject->setValue('data', array('group_id' => $group_id, participants_id => $new_participants_id, 'token' =>$user->getRelationship('device')->token));
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
	 
	 public function tokencheckAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
	 	// Create a database connection to share
		$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
		
		array_key_exists('token_id', $this->params)? $token_id= $this->params['token_id']: $token_id= '';
		//echo $token_id;							
		// authenticate token with stored procedure
		require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
		$user = SafetextUser::tokenToUser($token_id, $db, $this->config);
		
		if ($user instanceof SafetextUser && $user->isValid()) {
			$viewObject->setValue('status', 'success');
			$viewObject->setValue('data', array('user_id' => $user->id));
		}
		else { 
			$viewObject->setValue('status', 'fail');
		}
	 }
}