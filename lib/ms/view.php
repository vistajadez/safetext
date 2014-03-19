<?php

/**
 * Mediasoft View Object
 *
 *
 */
class MsView {
	
	protected $viewScript;				// path to view script
	protected $layout;					// path to layout script
	protected $layoutDisabled = false;	// toggle for option to disable layout
	protected $langValues;				// language values array for language-aware scripts
	protected $lang = 'en';
	protected $content = '';			// stores the feature content to be accessed by layout script
	protected $responseType = 'html'; 	// html returns html content, json returns json data, css return css
	protected $cacheExpireSeconds;		// seconds to instruct the client browser to store this content in cache
	protected $doctype = '<!DOCTYPE html>';
	protected $controllerName;
	protected $title = 'Mediasoft Technologies, Inc.'; 		// HTML page title
	
	protected $metaTags = array();		// HTML meta tags
	protected $scriptIncludes = array();	// js files to include
	protected $cssIncludes = array();		// css files to include
	
	protected $values = array();			// values to parse into viewscript
	protected $helpers = array();			// helper registry
	
	protected $jsoncallback;				// this will be set if a JSONP call is being made
	
	
	
	/**
	 * Set View Script
	 * 
	 *
	 */
	public function setViewScript($pathToViewScript) {
		$this->viewScript = $pathToViewScript;
	 }
	
	/**
	 * Set Title
	 * 
	 *
	 */
	public function setTitle($value) {
		$this->title = $value;
		 
	 }
	 
	/**
	 * Get Title
	 * 
	 * @return String
	 *
	 */
	public function getTitle() {
		return $this->title;
		 
	 }
	
	/**
	 * Set Value
	 * 
	 *
	 */
	public function setValue($key, $value) {
		$this->values[$key] = $value;
		 
	 }
	 
	 /**
	 * Get Value
	 * 
	 *
	 */
	public function getValue($key) {
		if (array_key_exists($key, $this->values))
			return $this->values[$key];
		else return '';
	 }
	 
	 /**
	 * Set Controller Name
	 * 
	 *
	 */
	public function setControllerName($value) {
		$this->controllerName = $value;
		 
	 }
	 
	/**
	 * Get Controller Name
	 * 
	 *
	 */
	public function getControllerName() {
		return $this->controllerName;
		 
	 }
	 
	
	/**
	 * Set Layout
	 * 
	 *
	 */
	public function setLayout($layoutName) {
		if (file_exists(MS_PATH_BASE . DS .'layouts'. DS . $layoutName . '.phtml')) {
			$this->layout = MS_PATH_BASE . DS .'layouts'. DS . $layoutName . '.phtml';
		}
	 }
	 
	/**
	 * Set Language
	 * 
	 *
	 */
	public function setLanguage($langCode) {
		if (!file_exists( MS_PATH_BASE . DS . 'assets' . DS . 'languages' . DS . $langCode . '.php' )) $langCode = 'en';
	
		// include language file
		include(MS_PATH_BASE . DS . 'assets' . DS . 'languages' . DS . $langCode . '.php');
		$this->langValues = $langValues;
		$this->lang = $langCode;
		
		// include controller-specific language file, if exists
		if (file_exists( MS_PATH_BASE . DS . 'assets' . DS . 'languages' . DS . $langCode . '_' . MS_MODULE . '_' . $this->controllerName . '.php' ))
			include(MS_PATH_BASE . DS . 'assets' . DS . 'languages' . DS . $langCode . '_' . MS_MODULE . '_' . $this->controllerName . '.php');
			
		if ((isset($controllerLangValues)) && (is_array($controllerLangValues))) $this->langValues = array_merge($langValues, $controllerLangValues);
			else $this->langValues = $langValues;
	 }
	 
	/**
	 * Set Response Type.
	 * 
	 *
	 */
	 public function setResponseType($format) {
		$this->responseType = strtolower($format);
	 }
	 
	/**
	 * Set Cache Expire Seconds.
	 * Sets the time in seconds to instruct the client browser to cache this content.
	 * 
	 * @param int $seconds
	 */
	 public function setCacheExpireSeconds($seconds) {
		$this->cacheExpireSeconds = $seconds;
	 }

	/**
	 * No Cache.
	 * Instructs browser not to cache this content.
	 * 
	 * @return void
	 */
	 public function noCache() {
	 	$this->cacheExpireSeconds = '';
	 
		header ("Expires: ".gmdate("D, d M Y H:i:s", time())." GMT");  
		header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");  
		header ("Cache-Control: no-cache, must-revalidate");  
		header ("Pragma: no-cache");
	 }
	 
	
	/**
	 * Set JSONP Callback
	 * 
	 *
	 */
	public function setJsonCallback($callback) {
		$this->jsoncallback = $callback;
	 }
	 
	/**
	 * Disable Layout
	 * 
	 *
	 */
	public function disableLayout() {
		$this->layoutDisabled = true;
	 }
	 
	/**
	 * Add Stylesheet
	 * 
	 *
	 */
	public function addStylesheet($styleFilename) {
		if (file_exists(MS_PATH_BASE . DS . 'assets' . DS . 'css' . DS . $styleFilename)) {
			if (!in_array('/assets/css/' . $styleFilename, $this->cssIncludes)) $this->cssIncludes[] = '/assets/css/' . $styleFilename;
		} else {
			if (strpos($styleFilename, 'http://') !== false) {
				if (!in_array($styleFilename, $this->cssIncludes)) $this->cssIncludes[] = $styleFilename;
			}
		}
	}
	
	/**
	 * Render Stylesheets
	 * 
	 *
	 */
	public function renderStylesheets() {
		foreach ($this->cssIncludes as $thisCssFile) {
			echo '<link href="' . $thisCssFile . '" rel="stylesheet" type="text/css">' . "\n";
		}
	}
	
	
	/**
	 * Add ScriptInclude
	 * 
	 *
	 */
	public function addScriptInclude($scriptName) {
		if (file_exists(MS_PATH_BASE . DS . 'assets' . DS . 'js' . DS . $scriptName)) {
			if (!in_array('/assets/js/' . $scriptName, $this->scriptIncludes)) $this->scriptIncludes[] = '/assets/js/' . $scriptName;
		}
	}
	
	
	/**
	 * Add External ScriptInclude
	 * 
	 *
	 */
	public function addExternalScriptInclude($scriptName) {
		if (!in_array($scriptName, $this->scriptIncludes)) $this->scriptIncludes[] = $scriptName;
	}
	
	
	/**
	 * Render Script Includes
	 * 
	 *
	 */
	public function renderScriptIncludes() {
		foreach ($this->scriptIncludes as $thisScript) {
			echo '<script type="text/JavaScript" src="' . $thisScript . '"></script>' . "\n";
		}
	}
	
	
	/**
	 * Translate
	 * 
	 *
	 */
	public function t($key) {
		// make sure a language has been set, or use default (en)
		if (!$this->langValues) $this->setLanguage('en');
		
		if (array_key_exists($key, $this->langValues)) return $this->langValues[$key];
			else return $key;
	}
	
	
	/**
	 * Catch-All to Load View Helper.
	 * 
	 * 
	 */
	public function __call($name, $arguments) {
		// is this helper already in the registry?
		if ($this->helpers['$name'] instanceof MsHelper) return $this->helpers['$name']->$name($arguments);

		// include the view helper file
		$helperFilepath = MS_PATH_BASE . DS . 'views' . DS . MS_MODULE . DS . 'helpers' . DS . ucfirst($name) . '.php';
		if (!file_exists($helperFilepath)) return 'Missing view helper: ' . $helperFilepath;
		
		require_once($helperFilepath);
		eval('$helperObject = new ' . ucfirst($name) . 'Helper($this);'); // get handle to instance of helper object
		if ($helperObject instanceof MsHelper) {
			$this->helpers['$name'] = $helperObject; // add new helper to registry so we can call it again if needed
			return $helperObject->$name($arguments);
		}
	}
	
	/**
	 * Send Cache Headers.
	 *
	 * Sends headers to instruct the client's browser to cache this content for a period of time, in seconds.
	 * This will only be done if this view's cache expiration has been set using setCacheExpireSeconds().
	 * 
	 * @return void
	 */
	public function sendCacheHeaders() {
		if ($this->cacheExpireSeconds < 1) return;
		header("Pragma: public");
		header("Cache-Control: maxage=". $this->cacheExpireSeconds);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time()+ $this->cacheExpireSeconds) . ' GMT');
	}
	
	
	/**
	 * Render
	 * 
	 * 
	 */
	public function render($viewscript = '') {
		if ($viewscript != '') {
			// rendering a specific viewscript within this view context
			if (file_exists(MS_PATH_BASE . DS . 'views' . DS . MS_MODULE . DS . 'scripts' . DS . $viewscript)) {
				ob_start(); // start output buffering
				include(MS_PATH_BASE . DS . 'views' . DS . MS_MODULE . DS . 'scripts' . DS . $viewscript);
				return ob_get_clean(); // retrieve from buffer and clear
			}
		}

		// if the response type is JSON, just return the json encoding of the values. No viewscript is needed
		if ($this->responseType == 'json') {
			header('Content-type: application/json; charset=UTF-8');
			if (isset($this->jsoncallback)) return $this->jsoncallback . '(' . json_encode($this->values) . ');'; // JSONP response
				else return json_encode($this->values);
		}
		
		// for the response type is HTML, use view script
		if (($this->responseType == 'html') || ($this->responseType == 'css')) {
			if (file_exists($this->viewScript)) {
				ob_start(); // Start output buffering
				include($this->viewScript);
				$this->content = ob_get_clean(); // store from buffer and clear
				
				if ($this->cacheExpireSeconds > 0) $this->sendCacheHeaders();
				if ($this->responseType == 'css') {
					header("Content-type: text/css");
					return MsUtils::compressCss($this->content);
				}
				if ($this->layoutDisabled) return $this->content;
				
				// render layout
				if (!$this->layout) $this->layout = MS_PATH_BASE . DS .'layouts'. DS . 'default.phtml';
				ob_start(); // Start output buffering
				include($this->layout);
				return ob_get_clean(); // store from buffer and clear
			} else {
				return 'Missing viewscript: ' . $this->viewScript;
			}
		}

	 }
	
	 
	 
	/**
	 * Partial
	 * Render a viewscript in an isolated view context, determined by passed parameters
	 * 
	 */
	 public static function partial($viewscript, $params) {
		// create a view context
		$viewContext = new MsView();
		$viewContext->setViewScript( MS_PATH_BASE . DS .'views'. DS . 'scripts' . DS . $viewscript); // set the view script
		$viewContext->setLanguage($this->lang); // set the language
		$viewContext->disableLayout();
		
		// apply params to context
		foreach ($params as $paramName => $paramVal)
			$viewContext->setValue($paramName, $paramVal);
		 
		 // return rendered value
		 return $viewContext->render();
	 }
	
	
	
	
	
	
}
