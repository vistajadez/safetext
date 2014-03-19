<?php

/**
 * Mediasoft Controller.
 *
 *
 */
abstract class MsController {
	protected $config;
	protected $params;
	
	
	function __construct(&$config_in, &$params_in) {
		$this->config = $config_in;
		$this->params = $params_in;
	}
	
	
	
	
	
	/**
	 * Forward.
	 * 
	 * Forwards request to the specified action method.
	 *
	 * @param MsView $viewObject The view object.
	 * @param string $action The method to forward to.
	 * @param string $controller (Optional) Controller to forward to.
	 *
	 * @return void
	 */
	 public function forward(&$viewObject, $action, $controller='') {
		 if ($controller == '') {
			// forward to action of the current controller
			$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . $viewObject->getControllerName() . DS . $action . '.phtml'); // reset the view script
			eval('$this->' . $action . 'Action($viewObject);');
		 } else {
			// forward to action of a different controller 
			$newController = strtolower($controller);
			if (!file_exists( MS_PATH_BASE . DS .'controllers'. DS . MS_MODULE . DS . $newController . '.php' )) {
				$newController = 'notfound';
				$action = 'nocontroller';
			}
			
			require_once ( MS_PATH_BASE . DS .'controllers'. DS . MS_MODULE . DS . $newController . '.php' );
			eval('$controllerObject = new ' . ucfirst($newController) . 'Controller($this->config, $this->params);'); // instantiate controller object
	
			$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . $newController . DS . $action . '.phtml'); // reset the view script
			
			// perform controller action
			$actionMethod = $action . 'Action';
			$controllerObject->$actionMethod($viewObject);
			
		 }
	 }
	
}