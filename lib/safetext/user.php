<?php

/**
 * SafeText User Object.
 * Represents an application user.
 *
 */
class SafetextUser extends MsModel {
	// Database
	public $dbTable = 'user'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('id');
	
	// Columns
	public $columns = 'id,username,firstname,lastname,email,phone,pass,date_added,date_last_pass_update,language,notifications_on,whitelist_only,enable_panic'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods

	
	
	
	
	// Methods unique to User Object
	
	/**
	  * Full Name.
	  *
	  * @return String The user's full name as first + space + last.
	  *
	  */
	 public function fullName() {
		return trim(trim($this->firstname) . ' ' . trim($this->lastname));
	 }
	
	
	/**
	  * Get ID Hash.
	  *
	  * Returns an encoded version of this user ID, suitable for storing into an auth cookie.
	  *
	  * @return String Encrypted user code in form [id]:[hashed id].
	  *
	  */
	 public function getIdHash() {
		if (!$this->isValid()) return '';
		
		// sanity check
		if ($this->id == '') return '';
		return $this->id . ':' . MsUtils::authHash($this->id);
	 }
	
	
	/**
	  * Purge.
	  *
	  * Deletes this instance and all related dependencies.
	  *
	  * @return bool
	  *
	  */
	 public function purge() {
		if (!$this->isValid()) return false;
		
		// sanity check
		if ($this->id == '') return false;
		
		// delete dependencies
//		$this->addRelationship('relationship_label', 'SafetextRelationshipClass', array('id' => 'fk'));
//		$dependency =$this->getRelationship('relationship_label');
//		$dependency->purge();
		
		
		// delete this user's db entry
		return parent::purge();
	 }
	
	
	
	
	
	
	// Class Methods
	
	/**
	  * Authenticate By Hashcode.
	  *
	  * @param String $idHash Encrypted user ID code, typically stored in a cookie.
	  * @param String $responseType (Optional) If this is set to 'json', no cookies will be set. Default is 'html'.
	  * @param MsDb	$db (Optional) Database connection. If none is passed, a new connection will be made.
	  * @param mixed[] $config Configuration array.
	  *
	  * @return SafetextUser Authenticated user object.
	  *
	  */
	public static function authenticate($idHash, $responseType='html', $db='', $config) {
		// validate auth hash
		$idHash = trim($idHash);
		if ($idHash == '') return false;
		
		$idHashArray = explode(':', $idHash);
		if ($idHashArray[1] != MsUtils::authHash($idHashArray[0])) return false;

		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
		
		// Try to instantiate the user
		require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'user.php' );
		$user = new SafetextUser($config, $db);
		$user->identify(array('id' => array('value' => $idHashArray[0], 'operator' => '=')));
		if (!$user->isValid()) return false;
		
		// set cookie if response type is html
		if ($responseType === 'html') {
			array_key_exists('uid', $_COOKIE) ? $existingCookieId = $_COOKIE['uid'] : $existingCookieId = '';
			if ($existingCookieId != $idHash) setcookie('uid', $idHash, time() + $config['loginCookieExpireSeconds'], '/'); // expire in 30 days
		}
		
		return $user;
		
	}
	
	/**
	  * Generate Token.
	  * Generates an auth token for a particular user and device. If the device already has a token, it is un-initialized and 
	  * any existing sync records are removed.
	  *
	  * @param String username
	  * @param String password
	  * @param String deviceSig
	  * @param String deviceDesc
	  * @param MsDb	$db (Optional) Database connection. If none is passed, a new connection will be made.
	  * @param mixed[] $config Configuration array.
	  *
	  * @return mixed[] Array: id=>user_id (or 0 if unsuccessful), token=>device auth token, msg=>error message (nor null if successful)
	  *
	  */
	public static function generateToken($username, $password, $deviceSig, $deviceDesc, $db='', $config) {
		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
	
		// required params
		if ($username === '' || $password === '' || $deviceSig === '' || $deviceDesc === '') 
			return array('id' => 0, 'msg' => 'Missing parameters before sending to stored procedure');
	
		// stored procedure call
		$result = $db->query("CALL generateToken('$username','$password','$deviceSig','$deviceDesc')")->fetch_assoc();
		
		return $result;
	}
	
	
	
}