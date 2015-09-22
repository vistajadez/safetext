<?php
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'clientcontroller.php' );

/**
 * Web Client Controller.
 *
 *
 */
class WebclientController extends SafetextClientController {

	/**
	 * Default Action.
	 * 
	 * Called when no action is defined. Will forward to home if logged in, otherwise to the login page.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function defaultAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if ($this->init($viewObject)) $this->forward($viewObject, 'messages');
			else $this->forward($viewObject, 'login', 'auth');
	 }
	
	 
	 
	/**
	 * Contacts Action.
	 * 
	 * Render web client dashboard contacts view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactsAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			//print_r($contactsArray);exit;
			// add blocked contacts flag
			array_key_exists('filter', $this->params) ? $filter = $this->params['filter']: $filter = '';
			
			//title
			if ($filter === 'blocked') $viewObject->setTitle('Blocked Contacts');
				else $viewObject->setTitle('Contacts');
				
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);	
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('contacts', $contacts);
			$viewObject->setValue('filter', $filter);
		}
	 }
	 
	 
	/**
	 * Messages Action.
	 * 
	 * Render web client dashboard messages view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function messagesAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			//count unread messages
			//$sql_unread = "SELECT count(is_read) as unread_msg FROM messages,participants WHERE participants.contact_id='". $this->user->getValue('id')."' AND participants.is_sender=0 AND messages.is_read=0 AND participants.message_id=messages.id";
			//$unread = current($this->db->query($sql_unread));
			
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}
			
			
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			//print_r($contactsArray);exit;
			// are we viewing a specific folder?
			array_key_exists('folder', $this->params) ? $folder = $this->params['folder']: $folder = 'conversations';
			
			$conversations = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			if ($folder === 'conversations') {
				// default is to show list of conversations. Get the list from DB and store in view
				$conversationsArray = $this->db->call("conversations('" . $this->user->getValue('id') . "','0','999999')");
				$conversations->load($conversationsArray);
				
			} else if ($folder==='sent' || $folder==='inbox' || $folder==='drafts' || $folder==='important') {
				$messagesArray = $this->db->call("messages('" . $this->user->getValue('id') . "','" . $folder . "','0','999999')");
				$messages = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
				$messages->load($messagesArray);
				$viewObject->setValue($folder, $messages);
				
			}
			//print_r($conversations);exit;
			//title
			
			//group messages
			$cipher = new SafetextCipher($this->config['hashSalt']);
			
			
			$groupMessagesArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			
			$i=0;
			foreach($groupMessagesArray as $this_group_message) {
			
				$g_message = $this->db->call("getWebGroupMessages2('" . $this_group_message['id'] . "')");
				
				$message = $this->db->call("getMessageDetails('" . $g_message[0]['message_id'] . "')");
				
				$content = @$cipher->decrypt($message[0]['content']);
				
				$f_sendername = $this->db->call("getGroupUsername('".$g_message[0]['sender_id']."','".$this->user->getValue('id')."')");
				
				$group_name = current($this->db->query("SELECT * FROM `groups` WHERE `id`='".$g_message[0]['group_id']."'"));;
				
				if($f_sendername[0]['name']!="")
				$sendername = $f_sendername[0]['name'];
				else
				$sendername = $f_sendername[0]['username'];
				
				$groupMessagesArray2[$i] = array_merge($g_message,array("group_name"=>$group_name[0]['group_name'],"participants_id"=>$group_name[0]['participants_id'],"group_id"=>$g_message[0]['group_id'],"content"=>$content,"sender_name"=>$sendername,"sent_date"=>$message[0]['sent_date'],"expire_date"=>$message[0]['expire_date']));
				
				$i++;	
			}
			//end
			
			//print_r($groupMessagesArray2);
			//die();
			
			$viewObject->setTitle(ucfirst($folder));
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			// store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('conversations', $conversations);
			$viewObject->setValue('groupmessages', $groupMessagesArray2);
			$viewObject->setValue('folder', $folder);
			$viewObject->setValue('contacts', $contacts);
			
		}
	 }
	 
	 public function messages2Action(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			//print_r($contactsArray);exit;
			// are we viewing a specific folder?
			array_key_exists('folder', $this->params) ? $folder = $this->params['folder']: $folder = 'conversations';
			
			$conversations = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			if ($folder === 'conversations') {
				// default is to show list of conversations. Get the list from DB and store in view
				$conversationsArray = $this->db->call("conversations('" . $this->user->getValue('id') . "','0','999999')");
				$conversations->load($conversationsArray);
				
			} else if ($folder==='sent' || $folder==='inbox' || $folder==='drafts' || $folder==='important') {
				$messagesArray = $this->db->call("messages('" . $this->user->getValue('id') . "','" . $folder . "','0','999999')");
				$messages = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
				$messages->load($messagesArray);
				$viewObject->setValue($folder, $messages);
				
			}
			//print_r($conversations);exit;
			//title
			
			//group messages
			$cipher = new SafetextCipher($this->config['hashSalt']);
			
			
			$groupMessagesArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			
			$i=0;
			foreach($groupMessagesArray as $this_group_message) {
			
				$g_message = $this->db->call("getWebGroupMessages2('" . $this_group_message['id'] . "')");
				
				$message = $this->db->call("getMessageDetails('" . $g_message[0]['message_id'] . "')");
				
				$content = @$cipher->decrypt($message[0]['content']);
				
				$f_sendername = $this->db->call("getGroupUsername('".$g_message[0]['sender_id']."','".$this->user->getValue('id')."')");
				
				$group_name = current($this->db->query("SELECT * FROM `groups` WHERE `id`='".$g_message[0]['group_id']."'"));;
				
				if($f_sendername[0]['name']!="")
				$sendername = $f_sendername[0]['name'];
				else
				$sendername = $f_sendername[0]['username'];
				
				$groupMessagesArray2[$i] = array_merge($g_message,array("group_name"=>$group_name[0]['group_name'],"participants_id"=>$group_name[0]['participants_id'],"group_id"=>$g_message[0]['group_id'],"content"=>$content,"sender_name"=>$sendername,"sent_date"=>$message[0]['sent_date'],"expire_date"=>$message[0]['expire_date']));
				
				$i++;	
			}
			//end
			
			$viewObject->setTitle(ucfirst($folder));
			
			
			
			// store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('conversations', $conversations);
			$viewObject->setValue('groupmessages', $groupMessagesArray2);
			$viewObject->setValue('folder', $folder);
			$viewObject->setValue('contacts', $contacts);
			
		}
	 }
	 
	 /**
	 * Group Message Contacts
	 */
	 public function groupcontactsAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			//print_r($contactsArray);exit;
			// add blocked contacts flag
			array_key_exists('filter', $this->params) ? $filter = $this->params['filter']: $filter = '';
			
			//title
			if ($filter === 'blocked') $viewObject->setTitle('Blocked Contacts');
				else $viewObject->setTitle('Contacts');
				
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}	
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			// set view data
			$viewObject->setValue('user_id', $this->user->getValue('id'));
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('contacts', $contacts);
			$viewObject->setValue('filter', $filter);
		}
	 }
	 
	 /* Group Edit */
	 
	 public function groupeditAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			
			array_key_exists('id', $this->params) ? $group_id = $this->params['id']: $group_id = '';
			
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			//print_r($contactsArray);die();;
			
			$check_group_name = current($this->db->call("checkGroupname('".$group_id."','" . $this->user->getValue('id') . "')"));
			
			$f_group_name = current($this->db->query("SELECT * FROM groups WHERE id='" . $group_id . "'"));
			
			if($check_group_name['group_name']!="") {
				$group_name = $check_group_name['group_name'];
			}
			else {
				$group_name = $f_group_name[0]['group_name'];
			}
			
			$group_member = explode(",",$f_group_name[0]['participants_id']);
			//print_r($group_member);die();
			
			
			// add blocked contacts flag
			array_key_exists('filter', $this->params) ? $filter = $this->params['filter']: $filter = '';
			
			//title
			$viewObject->setTitle($group_name);
				
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}	
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			// set view data
			$viewObject->setValue('user_id', $this->user->getValue('id'));
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('group_name', $group_name);
			$viewObject->setValue('group_id', $f_group_name[0]['id']);
			$viewObject->setValue('group_member', $group_member);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('contacts', $contacts);
			$viewObject->setValue('filter', $filter);
		}
	 }
	 
	 /* Group Messages */
	 
	 public function groupmessagesAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			
			$i=0;
			foreach($groupsArray as $this_group) {
				
				//echo $this_group['id'];
				$groupname = current($this->db->call("checkGroupname('".$this_group['id']."','" . $this->user->getValue('id') . "')"));
				
				$groupsArray2[$i] = array_merge($groupsArray[$i],array("group_user_name"=>$groupname['group_name']));
			$i++;
				
			}
			//print_r($groupsArray2);die();
			
			$groups->load($groupsArray2);
			
			//print_r($groups);die();
			//title
			if ($filter === 'blocked') $viewObject->setTitle('Blocked Contacts');
				else $viewObject->setTitle('Groups');
				
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}	
				
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('groups', $groups);
			$viewObject->setValue('filter', $filter);
		}
	 }
	 
	 
	/**
	 * Conversation Action.
	 * 
	 * Renders view of a conversation between the user and one contact.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function conversationAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			array_key_exists('contact', $this->params) ? $contact = $this->params['contact']: $contact = '';
			//get time zone
			array_key_exists('timezone', $this->params) ? $timezone = $this->params['timezone']: $timezone = '';
			$timezone_new = str_replace("-","/",$timezone);
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}
			
			//load lifetime
			$lifetime = current($this->db->query("SELECT `expiretime` FROM `users` WHERE `id`='" . $this->user->getValue('id') . "'"));
			
			//load bubble color
			$bubble = current($this->db->query("SELECT color FROM bubble_color WHERE user_id='" . $this->user->getValue('id') . "'"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			//print_r($contactsArray);
			
			// load the conversation
			$conversation = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$conversationArray = $this->db->call("conversation('" . $this->user->getValue('id') . "','" . $contact . "','0','999999')");
			$conversation->load($conversationArray);
			
			
			
			// mark all messages in the conversation as "read"
			foreach ($conversation as $this_message) {
				if ($this_message->is_read == '0') {
					if ($this_message->recipient == $this->user->getValue('id')) {
						$this_message->is_read = '1';
						$this_message->save($this->user->getValue('id')); // saves update and sync's to all participants' devices
					}
				}
			}
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
			$viewObject->setTitle($contactName);
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('conversation', $conversation);
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('lifetime', $lifetime[0]['expiretime']);
			$viewObject->setValue('timezone', $timezone_new);
			$viewObject->setValue('color', $bubble[0]['color']);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
		}
	 }
	 
	 
	 public function conversation2Action(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			array_key_exists('contact', $this->params) ? $contact = $this->params['contact']: $contact = '';
			//get time zone
			array_key_exists('timezone', $this->params) ? $timezone = $this->params['timezone']: $timezone = '';
			$timezone_new = str_replace("-","/",$timezone);
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			//print_r($contactsArray);
			
			// load the conversation
			$conversation = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$conversationArray = $this->db->call("conversation('" . $this->user->getValue('id') . "','" . $contact . "','0','999999')");
			$conversation->load($conversationArray);
			
			
			
			// mark all messages in the conversation as "read"
			foreach ($conversation as $this_message) {
				if ($this_message->is_read == '0') {
					if ($this_message->recipient == $this->user->getValue('id')) {
						$this_message->is_read = '1';
						$this_message->save($this->user->getValue('id')); // saves update and sync's to all participants' devices
					}
				}
			}
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
			$viewObject->setTitle($contactName);
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('conversation', $conversation);
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('timezone', $timezone_new);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
		}
	 }
	 
	 
	 /*----- Group Conversation -----*/
	 
	 
	 public function groupconversationAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			array_key_exists('contact', $this->params) ? $contact = $this->params['contact']: $contact = '';
			//get time zone
			array_key_exists('timezone', $this->params) ? $timezone = $this->params['timezone']: $timezone = '';
			$timezone_new = str_replace("-","/",$timezone);
			
			/* For Group */
			array_key_exists('group_id', $this->params) ? $group_id = $this->params['group_id']: $group_id = '';
			//get time zone
			array_key_exists('participants_id', $this->params) ? $participants_id = $this->params['participants_id']: $participants_id = '';
			
			$check_group_name = current($this->db->call("checkGroupname('".$group_id."','".$this->user->getValue('id')."')"));
			
			$f_group_name = current($this->db->query("SELECT * FROM groups WHERE id='" . $group_id . "'"));
			
			if($check_group_name['group_name']!="") {
				$group_name = $check_group_name['group_name'];
			}
			else {
				$group_name = $f_group_name[0]['group_name'];
			}
			
			// load group usernames
			
			$participants = explode(",",$participants_id);
							
							
			$key = array_search($this->user->getValue('id'), $participants);
			unset($participants[$key]);
							
			$participants = array_values($participants);
			$count_p = count($participants);
						
			for($i=0;$i<$count_p;$i++) {
							
			$f_username = current($this->db->call("getGroupUsername('".$participants[$i]."','".$this->user->getValue('id')."')"));
			if($f_username['name']!="")
			$username .= $f_username['name'];
			else
			$username .= $f_username['username'];
			if($i<($count_p-1))
			$username .= ",";
			}
			
			
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			//load lifetime
			$lifetime = current($this->db->query("SELECT `expiretime` FROM `users` WHERE `id`='" . $this->user->getValue('id') . "'"));
			
			
			//load bubble color
			$bubble = current($this->db->query("SELECT color FROM bubble_color WHERE user_id='" . $this->user->getValue('id') . "'"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			//print_r($contactsArray);
			
			
			// load the conversation
			$conversation = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$conversationArray = $this->db->call("getWebGroupMessages('" . $group_id . "')");
			// count total participants
			$count_participants = $this->db->call("getCountParticipants('" . $group_id . "')");
			$receipients = explode(",",$count_participants[0]['participants_id']);
			$tot_receipients = count($receipients);	
			
			$i=0;
			foreach($conversationArray as $this_conversation) {
			
			//update is_read value
			current($this->db->call("UpdateCountIsRead('".$this_conversation['message_id']."','".$this->user->getValue('id')."','".$this_conversation['sender_id']."')"));
			
			$tot_is_read = current($this->db->query("SELECT `count_is_read` FROM group_message WHERE `message_id` = '".$this_conversation['message_id']."' "));
			
			if($tot_is_read[0]['count_is_read']>=($tot_receipients-1)) {
			$update_is_read = current($this->db->query("UPDATE messages SET is_read=1 WHERE id='".$this_conversation['message_id']."' LIMIT 1"));
			}
			
			$f_image_name = current($this->db->call("getImage('".$this_conversation['image']."')"));
			
			$f_sender_name = current($this->db->call("getGroupUsername('".$this_conversation['sender_id']."','".$this->user->getValue('id')."')"));
			if($f_sender_name['name']!="")
			$sender_name = $f_sender_name['name'];
			else
			$sender_name = $f_sender_name['username'];
			
			$conversationArray2[$i] = array_merge($conversationArray[$i],array("sender_name"=>$sender_name,"image_name"=>$f_image_name['filename']));
			$i++;
			}
			if(!empty($conversationArray)) 
			$conversation->load($conversationArray2);
			
			//print_r($conversationArray2);die();
			//$conversation = current($this->db->query("SELECT * FROM  `group_message`,`messages` WHERE  `group_message`.`group_id`='".$group_id."' AND `group_message`.`message_id` = `messages`.`id`"));
			
			
			
			
			// mark all messages in the conversation as "read"
			/*foreach ($conversation as $this_message) {
				if ($this_message->is_read == '0') {
					if ($this_message->recipient == $this->user->getValue('id')) {
						$this_message->is_read = '1';
						$this_message->save($this->user->getValue('id')); // saves update and sync's to all participants' devices
					}
				}
			}*/
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
				
			$viewObject->setTitle($group_name);
			
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}	
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			

			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('conversation', $conversation);
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('lifetime', $lifetime[0]['expiretime']);
			$viewObject->setValue('timezone', $timezone_new);
			$viewObject->setValue('color', $bubble[0]['color']);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
			$viewObject->setValue('group_name', $group_name);
			$viewObject->setValue('participants_name', $username);
			$viewObject->setValue('group_id', $group_id);
		}
	 }
	 
	 
	 /*---end of group conversation---*/
	 
	 /* group conversation2 */
	 
	 public function groupconversation2Action(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			array_key_exists('contact', $this->params) ? $contact = $this->params['contact']: $contact = '';
			//get time zone
			array_key_exists('timezone', $this->params) ? $timezone = $this->params['timezone']: $timezone = '';
			$timezone_new = str_replace("-","/",$timezone);
			
			/* For Group */
			array_key_exists('group_id', $this->params) ? $group_id = $this->params['group_id']: $group_id = '';
			//get time zone
			array_key_exists('participants_id', $this->params) ? $participants_id = $this->params['participants_id']: $participants_id = '';
			
			$group_name = current($this->db->query("SELECT * FROM groups WHERE id='" . $group_id . "'"));
			
			// load group usernames
			
			$participants = explode(",",$participants_id);
			//print_r($participants);die();
			$count_p = count($participants);
			for($i=0;$i<$count_p;$i++) {
							
			$f_username = current($this->db->call("getGroupUsername('".$participants[$i]."','".$this->user->getValue('id')."')"));
			if($f_username['name']!="")
			$username .= $f_username['name'];
			else
			$username .= $f_username['username'];
			if($i<($count_p-1))
			$username .= ",";
			}
			
			
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			//load lifetime
			$lifetime = current($this->db->query("SELECT `expiretime` FROM `users` WHERE `id`='" . $this->user->getValue('id') . "'"));
			
			
			//load bubble color
			$bubble = current($this->db->query("SELECT color FROM bubble_color WHERE user_id='" . $this->user->getValue('id') . "'"));
			
			// load contacts
			//$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			//print_r($contactsArray);
			
			
			// load the conversation
			$conversation = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$conversationArray = $this->db->call("getWebGroupMessages('" . $group_id . "')");
			// count total participants
			$count_participants = $this->db->call("getCountParticipants('" . $group_id . "')");
			$receipients = explode(",",$count_participants[0]['participants_id']);
			$tot_receipients = count($receipients);
			$i=0;
			foreach($conversationArray as $this_conversation) {
			
			//update is_read value
			current($this->db->call("UpdateCountIsRead('".$this_conversation['message_id']."','".$this->user->getValue('id')."','".$this_conversation['sender_id']."')"));
			
			$tot_is_read = current($this->db->query("SELECT `count_is_read` FROM group_message WHERE `message_id` = '".$this_conversation['message_id']."' "));
			
			if($tot_is_read[0]['count_is_read']>=($tot_receipients-1)) {
			$update_is_read = current($this->db->query("UPDATE messages SET is_read=1 WHERE id='".$this_conversation['message_id']."' LIMIT 1"));
			}
			
			$f_image_name = current($this->db->call("getImage('".$this_conversation['image']."')"));
			
			$f_sender_name = current($this->db->call("getGroupUsername('".$this_conversation['sender_id']."','".$this->user->getValue('id')."')"));
			if($f_sender_name['name']!="")
			$sender_name = $f_sender_name['name'];
			else
			$sender_name = $f_sender_name['username'];
			
			$conversationArray2[$i] = array_merge($conversationArray[$i],array("sender_name"=>$sender_name,"image_name"=>$f_image_name['filename']));
			$i++;
			}
			$conversation->load($conversationArray2);
			
			//print_r($conversationArray2);die();
			//$conversation = current($this->db->query("SELECT * FROM  `group_message`,`messages` WHERE  `group_message`.`group_id`='".$group_id."' AND `group_message`.`message_id` = `messages`.`id`"));
			
			
			
			
			// mark all messages in the conversation as "read"
			/*foreach ($conversation as $this_message) {
				if ($this_message->is_read == '0') {
					if ($this_message->recipient == $this->user->getValue('id')) {
						$this_message->is_read = '1';
						$this_message->save($this->user->getValue('id')); // saves update and sync's to all participants' devices
					}
				}
			}*/
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
				
			$viewObject->setTitle($group_name[0]['group_name']);
			
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}	
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('conversation', $conversation);
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('lifetime', $lifetime[0]['expiretime']);
			$viewObject->setValue('timezone', $timezone_new);
			$viewObject->setValue('color', $bubble[0]['color']);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
			$viewObject->setValue('group_name', $group_name[0]['group_name']);
			$viewObject->setValue('participants_name', $username);
			$viewObject->setValue('group_id', $group_id);
		}
	 }
	 
	  
	/**
	 * Contact Action.
	 * 
	 * Renders edit contact view.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactAction(&$viewObject) {
		// forward to the login page if not logged in
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
		
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}
			
			array_key_exists('id', $this->params) ? $contact = $this->params['id']: $contact = '';
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			// if not in existing contacts, try to add as a new contact by id??
			
			// ** TODO ** //
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
			$viewObject->setTitle('Edit ' . $contactName);
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('contactObject', $contactObject);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
			
		}
	 }
	 
	 
	/**
	 * Contact Add.
	 * 
	 * Provide search field for new contacts, along with results for any passed contact search.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function contactaddAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			//count unread messages
			$unread_msg = current($this->db->call("getgroupisread('" .$this->user->getValue('id'). "')"));
			
			if($unread_msg['tot_unread']>0) {
			$unread_message = $unread_msg['tot_unread'];
			}
			else {
			$unread_message = 0;
			}
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			array_key_exists('q', $this->params) ? $query = $this->params['q']: $query = '';
			$results = null;
			
			if ($query !== '') {
				$contactsArray = $this->db->call("contactLookup('" . $this->user->getValue('id') . "','" . $query . "')");				
				$results = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
				$results->load($contactsArray);
			}
			
			//title
			$viewObject->setTitle('Add Contact');
			
			// load groups
			$groups = new SafetextModelCollection('SafetextMessage', $this->config, $this->db);
			$groupsArray = $this->db->call("getGroupDetails('" . $this->user->getValue('id') . "')");
			$groups->load($groupsArray);
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('unread_msg', $unread_message);
			$viewObject->setValue('count_group', count($groups));
			$viewObject->setValue('q', $query);
			$viewObject->setValue('results', $results);
		}
	 }
	 
	 
	
	 
}