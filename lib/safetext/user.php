<?php

/**
 * SafeText User Object.
 * Represents an application user.
 *
 */
class SafetextUser extends SafetextModel {
	// Database
	public $dbTable = 'user'; // corresponding tablename
	
	// Primary Key
	protected $pk = array('id');
	
	// Columns
	public $columns = 'id,username,firstname,lastname,email,phone,pass,date_added,date_last_pass_update,language,notifications_on,whitelist_only,enable_panic'; // columns to use for this instance (overrides parent class, which specifies '*')
	
	
	
	// Implementation Methods

	/**
	  * Purge.
	  *
	  * Deletes this instance and all related dependencies. Overridden to use stored procedure.
	  *
	  * @return bool
	  *
	  */
	 public function purge() {
		$result = current($this->db->CALL("deleteUser('" . $this->id . "')"));
		if ($result['status'] === 'success') return true;
			else return false;
	 }
	
	
	
	
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
	
	
	
	
	
	
	
	// Class Methods
	
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
	public static function generateToken($username, $password, $deviceSig, $deviceDesc, $iosId, $androidId, $db='', $config) {
		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
	
		// required params
		if ($username === '' || $password === '' || $deviceSig === '' || $deviceDesc === '') 
			return array('id' => 0, 'msg' => 'Missing parameters before sending to stored procedure');
	
		// cleanse input
		$username = SafetextModel::escapeForDb($username);
		$password = SafetextModel::escapeForDb($password);
		$deviceSig = SafetextModel::escapeForDb($deviceSig);
		$deviceDesc = SafetextModel::escapeForDb($deviceDesc);
		$iosId = SafetextModel::escapeForDb($iosId);
		$androidId = SafetextModel::escapeForDb($androidId);
	
		// stored procedure call
		$result = current($db->call("generateToken('$username','$password','$deviceSig','$deviceDesc','$iosId','$androidId')"));
		
		return $result;
	}
	
	/**
	  * Expire Token.
	  * Expires a user's auth token.
	  *
	  * @param String token
	  *
	  * @return void
	  *
	  */
	public static function expireToken($token, $db='', $config) {
		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
	
		// required params
		if ($token === '') return;
		$token = SafetextModel::escapeForDb($token);
	
		// stored procedure call
		$db->call("expireToken('$token')");
	}
	
	
	/**
	  * Token to User.
	  *
	  * Given an auth token, calls a stored procedure to validate the token and return the associated user fields, along
	  * with fields of user dependency objects. Returns a SafetxtUser instance representing the data.
	  *
	  * If the token does not validate, returns an empty (invalid) SafetextUser instance.
	  *
	  * @param String token
	  *
	  * @return SafetextUser with linked relationship: SafetextDevice.
	  *
	  */
	public static function tokenToUser($token, $db='', $config) {
		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
	
		// required params
		if ($token === '') return;
	
		// stored procedure call
		$token = SafetextModel::escapeForDb($token);
		$result = current($db->CALL("tokenToUser('$token')"));
		
		// instantiate the user and dependencies
		require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'device.php' );
		$user = new SafetextUser($config, $db);
		$device = new SafetextDevice($config, $db);
		if (!array_key_exists('id', $result) || $result['id'] < 1) return $user; // invalid token
		
		// load user & dependency details
		foreach ($result as $column => $val) {
			if (strpos($column, '.') === false) $user->setValue($column, $val);
			else if (strpos($column, 'device.') !== false) $device->setValue(str_replace('device.', '', $column), $val);
		}
		$device->setValue('user_id', $user->id);
		$user->unchanged();
		$device->unchanged();
		
		// set up relationship in model
		$user->relate('device',$device);
		
		return $user;
	}
	
	
	/**
	  * New User.
	  * Creates a new user and sets up a device for the user, then returns the device's auth token.
	  *
	  * @param String username
	  * @param String password
	  * @param String name
	  * @param String email
	  * @param String deviceSig
	  * @param String deviceDesc
	  * @param MsDb	$db (Optional) Database connection. If none is passed, a new connection will be made.
	  * @param mixed[] $config Configuration array.
	  *
	  * @return mixed[] Array: id=>user_id (or 0 if unsuccessful), token=>device auth token, msg=>error message (nor null if successful)
	  *
	  */
	public static function newUser($username, $password, $name, $email, $deviceSig, $deviceDesc, $iosId, $androidId, $db='', $config) {
		// make sure we have a db connection
		if (!$db instanceof MsDb) $db = new MsDb($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName']);
	
		// required params
		if ($username === '' || $password === '' || $deviceSig === '' || $deviceDesc === '') 
			return array('id' => 0, 'msg' => 'Missing parameters before sending to stored procedure');
	
		// cleanse input
		$nameArray = explode(' ', $name, 2);
		$username = SafetextModel::escapeForDb($username);
		$password = SafetextModel::escapeForDb($password);
		$firstname = SafetextModel::escapeForDb($nameArray[0]);
		$lastname = SafetextModel::escapeForDb($nameArray[1]);
		$email = SafetextModel::escapeForDb($email);
		$deviceSig = SafetextModel::escapeForDb($deviceSig);
		$deviceDesc = SafetextModel::escapeForDb($deviceDesc);
		$iosId = SafetextModel::escapeForDb($iosId);
		$androidId = SafetextModel::escapeForDb($androidId);
	
		// stored procedure call
		$result = current($db->call("newUser('$username','$password','$firstname','$lastname','$email','$deviceSig','$deviceDesc','$iosId','$androidId')"));
		
		return $result;
	}
	
	
}