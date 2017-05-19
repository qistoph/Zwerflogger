<?php
// Requires phpqrcode (Debian: apt-get install phpqrcode)
// http://phpqrcode.sourceforge.net/

include('../config.php');
include(Config::$qrlib_path);

if(isset($_REQUEST['beacon'])) {
	$qrurl = Config::$QR_baseURL . $_REQUEST['beacon'];
	QRcode::png($qrurl, false, Config::$QR_eclevel, Config::$QR_pixel_size, Config::$QR_margin);
	exit;
}

if(!isset($_REQUEST['hide']) or $_REQUEST['hide'] != Config::$hide_secret) {
	exit;
}

?>
<!doctype html>
<html>
<head>
	<title>CyberZwerftoch - Welcome</title>

	<style>
	html,body {
		font-family: Arial, Verdana, sans-serif;
	}

	.card {
		border: solid black 1pt;
		display: inline-block;
		margin: 20pt;
		padding: 1pt;
		page-break-inside: avoid;

		text-align: center;
		font-weight: bold;
		vertical-align: middle;
	}

	h1 {
		margin: 5pt;
		padding: 0;
		font-size: 120%;
		font-weight: bold;
		vertical-align: middle;
	}
	</style>
</head>
<body>
<?php
$db = new SQLite3('../zwerfdata.db', SQLITE3_OPEN_READWRITE);

$stmt = $db->prepare('SELECT beaconid, tag FROM beacons');
$result = $stmt->execute();
while(($beacon = $result->fetchArray()) !== FALSE) {
	printf('<div class="card"><h1>Cyber Zwerftocht</h1><img src="qr.php?beacon=%s"><br>%s</div>', $beacon['beaconid'], $beacon['tag']);
}
?>
</body>
</html>
