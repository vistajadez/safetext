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
			// load stats for all contacts and messages for current user
			$folderStats = current($this->db->call("folderStats('" . $this->user->getValue('id') . "')"));
			
			// load all registered devices
			$devicesArray = $this->db->call("devices('" . $this->user->getValue('id') . "')");
			$devices = new SafetextModelCollection('SafetextDevice', $this->config, $this->db);
			$devices->load($devicesArray);
			
			// load membership levels
			$subscriptionLevels = $this->db->call("subscriptionLevels()");

			//title
			$viewObject->setTitle('Settings');
			
			// set view data
			$viewObject->setValue('folderStats', $folderStats);
			$viewObject->setValue('devices', $devices);
			$viewObject->setValue('subscription_levels', $subscriptionLevels);
		
		
		
		}
	 }
	
	
	
	
}