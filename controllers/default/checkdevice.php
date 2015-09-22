<?php
/**
 * Controller for Webservices REST API version 1.
 *
 *
 * This is the controller for the SafeText Webservices REST API, version 1.
 *
 */
class CheckdeviceController extends MsController  {

	public function checkAction(&$viewObject) {
	 	$viewObject->setResponseType('json');
	 	$db = new MsDb($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass'], $this->config['dbName']);
		array_key_exists('ios_id', $this->params)? $ios_id = $this->params['ios_id']: $ios_id = '';
		array_key_exists('android_id', $this->params)? $android_id = $this->params['android_id']: $android_id = '';
		
		if($ios_id!="") {
		$result = current($db->query("SELECT is_initialized FROM sync_device WHERE ios_id='".$ios_id."'"));
		$result1 = current($db->query("UPDATE sync_device SET is_initialized=0 WHERE ios_id='".$ios_id."' AND is_initialized=1"));
		}
		if($android_id!="") {
		$result = current($db->query("SELECT is_initialized FROM sync_device WHERE android_id='".$android_id."'"));
		$result1 = current($db->query("UPDATE sync_device SET is_initialized=0 WHERE android_id='".$android_id."' AND is_initialized=1"));
		}
		
		if($result[0]['is_initialized']==1)
		{
			$status = 1;
		}
		else
		{
			$status = 0;
		}
		if($ios_id!="") {
		$viewObject->setValue('ios_id', $ios_id);
		}
		if($android_id!="") {
		$viewObject->setValue('android_id', $android_id);
		}
		$viewObject->setValue('status', $status);
	 }

}