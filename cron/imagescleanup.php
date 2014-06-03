<?php

/**
 * Images Cleanup
 *
 * This script is to be called from a cron job of the web user. It handles expiration
 * of images on a regular schedule
 * @copyright  Copyright (C) 2014 Mediasoft Technologies, Inc., All Rights Reserved.
 * @author     Jason Melendez.
 * @link       http://www.safe-text.com.
 *
 */
 

// constants
define( 'MS_PATH_BASE', dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ); // i.e. /var/www/myapp (parent directory)
define('DS', DIRECTORY_SEPARATOR );

// config vars
require_once ( MS_PATH_BASE . DS . 'config.php' );

// core lib files
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'utils.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'controller.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'view.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'helper.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'model.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'modelcollection.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'ms' . DS . 'db.php' );

// log this cron job start
$entry = "\n" . date('Y-m-d G:i:s') . ': Starting job ' . __FILE__ . "\n";
file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
		
		
// Create a database connection to share
$db = new MsDb($ms_config['dbHost'], $ms_config['dbUser'], $ms_config['dbPass'], $ms_config['dbName']);

// call maintenance procedure
$result = $db->call("imagesCleanup()");
if ($result['filename'] != '') {
	$entry = 'Deleting image ' . $result['filename'] . "*\n";
	
	if (file_exists(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-l.jpg'))
		unlink(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-l.jpg');
	if (file_exists(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-m.jpg'))
		unlink(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-m.jpg');
	if (file_exists(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-s.jpg'))
		unlink(MS_PATH_BASE . DS . 'assets' . DS . 'images' . DS . 'users' . DS . $result['filename'] . '-s.jpg');
}

file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);


// log this cron job finish
$entry = date('Y-m-d G:i:s') . ': Completing job ' . __FILE__ . "\n";
file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
