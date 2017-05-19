<?php
// Requires phpqrcode (Debian: apt-get install phpqrcode)
// http://phpqrcode.sourceforge.net/

include('../config.php');
include(Config::$qrlib_path);

if(isset($_REQUEST['field']) && isset($_REQUEST['value'])) {
	$qrurl = sprintf('%s?%s=%s', Config::$QR_baseURL, urlencode($_REQUEST['field']), urlencode($_REQUEST['value']));
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
<h1>Teams</h1>
<?php
$db = new SQLite3('../zwerfdata.db', SQLITE3_OPEN_READWRITE);

$stmt = $db->prepare('SELECT teamid, name FROM teams');
$result = $stmt->execute();
while(($team = $result->fetchArray()) !== FALSE) {
	printf('<div class="card"><h1>%s</h1><img src="qr.php?field=teamid&value=%s"></div>', $team['name'], $team['teamid']);
}

?>
<h1>Beacons</h1>
<?php
$stmt = $db->prepare('SELECT beaconid, tag FROM beacons');
$result = $stmt->execute();
while(($beacon = $result->fetchArray()) !== FALSE) {
	printf('<div class="card"><h1>Cyber Zwerftocht</h1><img src="qr.php?field=beacon&value=%s"><br>%s</div>', $beacon['beaconid'], $beacon['tag']);
}
?>
</body>
</html>
