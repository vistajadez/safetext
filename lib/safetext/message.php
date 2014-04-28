<?php

/**
 * SafeText Message Object.
 * Represents a message.
 *
 */
class SafetextMessage extends SafetextModel {
	
	// Implementation Methods

	/**
	  * Save.
	  *
	  * Persist updated column values and relationships to database. Overridden to use stored procedure as well as sync update to
	  * all participants' devices.
	  *
	  * @param String userIdIn ID of user updating the message.
	  *
	  * @return void
	  *
	  */
	public function save($userIdIn = '')
	{
		if (($this->isValid()) && (sizeof($this->changedColumns) > 0) && ($userIdIn !== '')) {
			// save/sync via stored procedure
			$this->db->call("syncMessage('" . $userIdIn . "','" . $this->id . "','" . $this->is_important . "','" . $this->is_draft . "','" . $this->is_read . "')");

			// reset change tracker
			$this->unchanged();
		}
	}
	
	
	// Methods unique to Message Object
	
	
	
	
}