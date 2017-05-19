<?php

class Config {
	static function init() {
		self::$db_file = '../zwerfdata.db'; // Relative to web folder (or absolute)
		self::$session_name = ''; // Can use used to handle multiple instances
		self::$secure_cookies = true; // Only set true if using HTTPS
		self::$qrlib_path = '/usr/share/phpqrcode/qrlib.php'; // Absolute, or
		//self::$qrlib_path = '../lib/phpqrcode/qrlib.php';  // Relative to web folder

		self::$hide_secret = ''; // Use your own random secret here

		self::$QR_eclevel = 0; // 0 = L, 1 = M, 2 = Q, 3 = H
		self::$QR_pixel_size = 4;
		self::$QR_margin = 2;

		// Feel free to adjust, e.g. when using a proxy or non-default port
		self::$QR_baseURL = sprintf('%s://%s%s/',
			$_SERVER['HTTPS'] == 'on' ? 'https' : 'http',
			$_SERVER['HTTP_HOST'],
			dirname($_SERVER['REQUEST_URI']));
	}

	static $db_file;
	static $session_name;
	static $secure_cookies;
	static $qrlib_path;
	static $hide_secret;
	static $QR_eclevel;
	static $QR_pixel_size;
	static $QR_margin;
	static $QR_baseURL;
}

Config::init();
