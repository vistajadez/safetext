<?php

/**
 * SafeText Contact Object.
 * Represents a user's contact.
 *
 */
class SafetextContact extends SafetextModel {
	
	// Implementation Methods


	
	
	// Methods unique to Contact Object
	
	/**
	  * Full Name.
	  *
	  * @return string
	  *
	  */
	public function label()
	{ 
		// first try to return the contact's name, if it exists
		if (trim($this->getValue('name')) !== '')
			return trim($this->getValue('name'));
			
		// if no name exists, try to return the contact's email address
		if ($this->getValue('email') !== '') return $this->getValue('email');
		
		// if no email exists, try to return the contact's phone number
		if ($this->getValue('phone') !== '') return $this->getValue('phone');
		
		// else return a default value
		return 'Unnamed Contact';
	}
	
	
}