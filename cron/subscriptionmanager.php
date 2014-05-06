<?php

/**
 * Subscription Manager
 *
 * This script is to be called from a cron job of the web user. It handles expiration
 * of user accounts that have exceeded their membership period. Should be run on a daily
 * basis.
 *
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
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'model.php' );
require_once ( MS_PATH_BASE . DS . 'lib' . DS . 'safetext' . DS . 'modelcollection.php' );

// log this cron job start
$entry = "\n" . date('Y-m-d G:i:s') . ': Starting job ' . __FILE__ . "\n";
file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
		
		
// Create a database connection to share
$db = new MsDb($ms_config['dbHost'], $ms_config['dbUser'], $ms_config['dbPass'], $ms_config['dbName']);

// 1. Process expirations
// get an array of all the expiring subscriptions
$result = $db->call("getExpiringAccounts()");

// process each expiring account (i.e. send them an email or notification)
foreach ($result as $this_user) {
	// *
	// * TODO
	// *
	$entry = ' - account expired: ' . $this_user['id'] . "\n";
	file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
}

// Expire all the accounts
$db->call("expireAccounts()");


// 2. Process renewals
// get an array of all the expiring subscriptions
$renewals = $db->call("getRenewingAccounts()");

if (sizeof($renewals) > 0) {
	$subscriptionLevels = $db->call("subscriptionLevels()");
	
	// obtain a PayPal authorization token
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $ms_config['paypal']['endpoint'] . '/v1/oauth2/token');
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch, CURLOPT_USERPWD, $ms_config['paypal']['clientid'] . ':' . $ms_config['paypal']['secret']);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
	
	$result = curl_exec($ch);
	
	// process response
	$response = json_decode($result, true);						
	if (is_array($response) && array_key_exists('access_token', $response) && $response['access_token'] !== '') {
		$authtoken = $response['access_token'];
		curl_close($ch);
		
		// process each expiring account
		foreach ($renewals as $this_user) {
			// determine selected subscription options details
			
			$selectedSubscription = null;
			foreach ($subscriptionLevels as $this_subscriptionLevel) {
				if ($this_subscriptionLevel['id'] === $this_user['subscription_level']) $selectedSubscription = $this_subscriptionLevel;
			}
							
			// get credit card details for this account
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $ms_config['paypal']['endpoint'] . '/v1/vault/credit-card/' . $this_user['payment_token']);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer ' . $authtoken,
				'Accept: application/json',
				'Content-Type: application/json'
			));
			$result = curl_exec($ch);
		
			// process response
			$response = json_decode($result, true);						
			if (!is_array($response) || !array_key_exists('state', $response) || $response['state'] !== 'ok') {
				// unable to get credit card details, so cancel the user's renewal status. Will expire tomorrow if card is not updated
				$db->call("updateSubscription('" . $this_user['id'] . "','" . $this_user['subscription_level'] . "','" . $this_user['payment_token'] . "','" . $this_user['subscription_expires'] . "', '0')");
				
				// send notification to user
				// *
				// * TODO
				// *
				
				$entry = 'ERROR: Unable to get credit card details for user ' . $this_user['id'] . ', so unable to renew' . "\n";
				file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
				curl_close($ch);
				
			} else {
				// save some values for later calls
				$lastfour = substr($response['number'], -4);
				$type = $response['type'];
				$expire_month = $response['expire_month'];
				$expire_year = $response['expire_year'];
				curl_close($ch);
				
				// make subscription payment
				if (isset($selectedSubscription)) {
					$data = array(
						'intent' => 'sale',
						'payer' => array(
							'payment_method' => 'credit_card',
							'funding_instruments' => array(
								array(
									'credit_card_token' => array(
										'credit_card_id' => $this_user['payment_token'],
										'payer_id' => $this_user['id'],
										'last4' => $lastfour,
										'type' => $type,
										'expire_month' => $expire_month,
										'expire_year' => $expire_year
									)
								)
							)
						),
						'transactions' => array (
							array(
								'amount' => array(
									'total' => $selectedSubscription['cpm'],
									'currency' => 'USD'
								),
								'description' => 'Safe-Text Membership Renewal'
							)
						)
					);
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $ms_config['paypal']['endpoint'] . '/v1/payments/payment');
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(
						'Authorization: Bearer ' . $authtoken,
						'Accept: application/json',
						'Content-Type: application/json'
					));
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
					$result = curl_exec($ch);
					
					// process response
					$response = json_decode($result, true);						
					if (!is_array($response) || !array_key_exists('state', $response) || $response['state'] !== 'approved') {
						// payment failed, so cancel the user's renewal status. Will expire tomorrow if card is not updated
						$db->call("updateSubscription('" . $this_user['id'] . "','" . $this_user['subscription_level'] . "','" . $this_user['payment_token'] . "','" . $this_user['subscription_expires'] . "', '0')");
						
						// send notification to user
						// *
						// * TODO
						// *
						
						$entry = 'NOTICE: renewal payment FAILED for ' . $this_user['id'] . ', so unable to renew. Raw response: ' . $result . "\n";
						file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
						curl_close($ch);
						
					} else {
					
						// store payment details
						$db->call("putPayment('" . $response['id'] . "','" . $this_user['id'] . "','" . $selectedSubscription['cpm'] . "','" . $response['state'] . "')");	
						
						// determine new expiration date
						if ($this_user['subscription_expires'] === '0000-00-00 00:00:00')
							$expireDate = new DateTime();
						else 
							$expireDate = new DateTime($this_user['subscription_expires']);
						
						$expireDate->add(new DateInterval('P' . ($selectedSubscription['months'] * 30) . 'D'));
						
						// save updated expiration date
						$db->call("updateSubscription('" . $this_user['id'] . "','" . $selectedSubscription['id'] . "','" . $this_user['payment_token'] . "','" . $expireDate->format('Y-m-d') . "', '1')");
					
						
						$entry = ' * auto-renewed account: ' . $this_user['id'] . ', payment ID: ' . $response['id'] . "\n";
						file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
						curl_close($ch);
					}
				} else { // unable to find selected subscription
					$db->call("updateSubscription('" . $this_user['id'] . "','" . $this_user['subscription_level'] . "','" . $this_user['payment_token'] . "','" . $this_user['subscription_expires'] . "', '0')");
					$entry = 'ERROR: unable to determine subscription level for user ' . $this_user['id'] . ', so unable to renew' . "\n";
					file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
				}
								
			} // end if able to get user's credit card details
		} // end looping through renewals
	} else {
		$entry = 'ERROR: Unable to get auth token from PayPal, so unable to process due renewals. Raw response: ' . $result . "\n";
		file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
	}
} // end if sizof renewals > 0



// log this cron job finish
$entry = date('Y-m-d G:i:s') . ': Completing job ' . __FILE__ . "\n";
file_put_contents(MS_PATH_BASE . DS . 'cron' . DS . 'cron.log', $entry, FILE_APPEND);
