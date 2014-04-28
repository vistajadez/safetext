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
		if ($this->init($viewObject)) $this->forward($viewObject, 'home');
			else $this->forward($viewObject, 'login', 'auth');
	 }
	
	 
	 
	/**
	 * Home Action.
	 * 
	 * Render web client dashboard home.
	 *
	 * @param MsView $viewObject
	 * @return void
	 */
	 public function homeAction(&$viewObject) {
		// forward to the web client page if logged in, otherwise to the login page
		if (!$this->init($viewObject)) {
			$this->forward($viewObject, 'login', 'auth');
		} else {
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
		
		
		
			// TODO
		
		
		
			$viewObject->setValue('folderStats', $folderStats);
			
		}
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
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			//title
			$viewObject->setTitle('Contacts');
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('contacts', $contacts);
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
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
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
			
			//title
			$viewObject->setTitle(ucfirst($folder));
			
			// store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('conversations', $conversations);
			$viewObject->setValue('folder', $folder);
			$viewObject->setValue('contacts', $contacts);
			
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
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
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
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('conversation', $conversation);
			$viewObject->setValue('contact', $contact);
			$viewObject->setValue('contactName', $contactName);
			$viewObject->setValue('contacts', $contacts);
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
			array_key_exists('id', $this->params) ? $contact = $this->params['id']: $contact = '';
			
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load contacts
			$contactsArray = $this->db->call("contacts('" . $this->user->getValue('id') . "','name','0','999999')");
			$contacts = new SafetextModelCollection('SafetextContact', $this->config, $this->db);
			$contacts->load($contactsArray);
			
			// if not in existing contacts, try to add as a new contact by id??
			
			// ** TODO ** //
			
			//title
			$contactObject = $contacts->find('contact_user_id', $contact);
			if ($contactObject instanceof SafetextContact) $contactName = $contactObject->label();
				else $contactName = 'Unknown';
			$viewObject->setTitle('Edit ' . $contactName);
			
			//store data in view
			$viewObject->setValue('folderStats', $folderStats);
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
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('q', $query);
			$viewObject->setValue('results', $results);
		}
	 }
	 
}