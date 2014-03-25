<?php

/**
 * Mediasoft Framework Bootstrap & Router.
 *
 * @copyright  Copyright (C) 2013 Mediasoft Technologies, Inc., All Rights Reserved.
 * @author     Jason Melendez.
 * @link       http://www.mediasofttechnologies.com.
 * @link       http://www.github.com/deztopia.
 *
 */
 
// config vars
require_once ( 'config.php' );

// constants
define('MS_PATH_BASE', dirname(__FILE__) ); // i.e. /var/www/myapp
define('MS_URL_BASE', 'http://' . $_SERVER["SERVER_NAME"]); // i.e. http://myapp.com
define('DS', DIRECTORY_SEPARATOR );
array_key_exists('CONTENT_TYPE', $_SERVER) ? define('MS_CONTENT_TYPE', $_SERVER['CONTENT_TYPE']) : define('MS_CONTENT_TYPE', '');
array_key_exists('REQUEST_METHOD', $_SERVER) ? define('MS_REQUEST_METHOD', $_SERVER['REQUEST_METHOD']) : define('MS_REQUEST_METHOD', '');
if (array_key_exists('HTTPS', $_SERVER) && $_SERVER['HTTPS'] != "")  define('MS_PROTOCOL', 'https'); else define('MS_PROTOCOL', 'http');
// MS_MODULE defined later
// MS_CONTROLLER defined later
// MS_ACTION defined later

// core lib files - feel free to remove anything you won't need
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'utils.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'controller.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'view.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'helper.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'model.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'modelcollection.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'db.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'model.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'log.php' );

// parse route
$urlArray = explode('?', trim($_SERVER['REQUEST_URI']));
$url = explode('/', trim($urlArray[0], '/'));
$controller = array_shift($url);
$action = array_shift($url);

// organize the parameters
$params = array();
$name = '';
foreach ($url as $this_param) {
	if ($name == '') $name = urldecode($this_param);
	else {
		$params[$name] = urldecode($this_param);
		$name = '';
	}
}
foreach ($_REQUEST as $key => $val) {
	$params[$key] = $val;	
}
if ($ms_config['addCookiesToParams']) {
	foreach ($_COOKIE as $key => $val) {
		$params[$key] = $val;	
	}
}
// JSON in the RAW POST data?
if (MS_CONTENT_TYPE == 'application/json') {
	$jsonRequest = json_decode(file_get_contents('php://input'), true);
	if ($jsonRequest !== NULL) foreach ($jsonRequest as $key => $val) $params[$key] = $val;
}


$params['domain'] = str_replace('www.', '', $_SERVER['HTTP_HOST']);	// Remove 'www' from domain i.e. mysite.com, or m.mysite.com instead of www.mysite.com or www.m.mysite.com

// logging
$log = new SafetextLog();
$ms_config['log'] = $log;

// route module based on the URL (or you can adjust to base on a parameter). THIS IS OPTIONAL - you may not need to separate your application into modules
//if ($params['domain'] == $ms_config['otherModuleUrl']) define('MS_MODULE', 'othermodule' );
//	else define('MS_MODULE', 'default' );
define('MS_MODULE', 'default' );

// route controller
if (!$controller) $controller='default';
$controller = strtolower(str_replace('-', '', $controller)); // determine controller
define('MS_CONTROLLER', $controller );

if (!file_exists( MS_PATH_BASE . DS .'controllers'. DS . MS_MODULE . DS . $controller . '.php' )) {
	$controller = 'notfound';
	$action = 'nocontroller';
}

require_once ( MS_PATH_BASE . DS .'controllers'. DS . MS_MODULE . DS . $controller . '.php' );
eval('$controllerObject = new ' . ucfirst($controller) . 'Controller($ms_config, $params);'); // instantiate controller object


// route action
if (!$action) $action = 'default';
$action = strtolower(str_replace('-', '', $action)); // determine action
define('MS_ACTION', $action );

if (!method_exists($controllerObject, $action . 'Action')) {
	if (method_exists($controllerObject, '__Action')) {
		// controller has an optional catch action
		$action = '__';	
	} else {
		require_once ( MS_PATH_BASE . DS .'controllers'. DS . MS_MODULE . DS . 'notfound.php' );
		eval('$controllerObject = new NotfoundController($ms_config, $params);');
		$controller = 'notfound';
		$action = 'noaction';
	}
}
$actionMethod = $action . 'Action';


// create and configure the view object
$viewObject = new MsView();
$viewObject->setViewScript( MS_PATH_BASE . DS .'views'. DS . MS_MODULE . DS . 'scripts' . DS . $controller . DS . $action . '.phtml'); // set the view script
$viewObject->setControllerName($controller);
if (isset($params['lang'])) $viewObject->setLanguage($params['lang']); // set the language
if (isset($params['format'])) $viewObject->setResponseType($params['format']); // set the response type

// perform controller action
$controllerObject->$actionMethod($viewObject);

// apply layout
if (isset($params['layout'])) $viewObject->setLayout($params['layout']);

// apply styles
if (isset($params['style'])) $viewObject->addStylesheet(strtolower($params['style']) . '.css');

// set jsoncallback param for jsonp request
if (isset($params['callback'])) $viewObject->setJsonCallback($params['callback']);

echo $viewObject->render();