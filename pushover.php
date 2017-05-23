<?php
// Requires: php-curl
require_once('config.php');

function pushover_send($message) {
	if(empty(Config::$pushover_app_token) || empty(Config::$pushover_group_id)) {
		error_log('Pushover message failed, pushover_app_token and/or pushover_group_id not configured.', 0);
		return false;
	}

	curl_setopt_array($ch = curl_init(), array(
		CURLOPT_URL => "https://api.pushover.net/1/messages.json",
		CURLOPT_POSTFIELDS => array(
			"token" => Config::$pushover_app_token,
			"user" => Config::$pushover_group_id,
			"message" => $message,
			"html" => 1,
			"url" => Config::$baseURL,
		),
		CURLOPT_SAFE_UPLOAD => true,
		CURLOPT_RETURNTRANSFER => true,
	));

	$res = curl_exec($ch);
	curl_close($ch);

	if($res === FALSE) {
		error_log('Pushover message failed, curl_exec returned false', 0);
		return false;
	}

	$res = json_decode($res);
	if($res === NULL) {
		error_log('Pushover message failed, invalid json', 0);
		return false;
	}

	if($res->status == 0) {
		error_log('Pushover message failed, errors: '.join(', ', $res->errors), 0);
		return false;
	}

	return true;
}
