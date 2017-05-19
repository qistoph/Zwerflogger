<?php
// Requires phpqrcode (Debian: apt-get install phpqrcode)
// http://phpqrcode.sourceforge.net/

include "/usr/share/phpqrcode/qrlib.php";
//include "lib/phpqrcode/qrlib.php";

$hide_secret = '4605cd88b10f9a812015'; // Use your own random secret here
$eclevel = QR_ECLEVEL_L; // _L, _M, _Q, _H
$pixel_size = 4;
$margin = 2;
// Feel free to adjust, e.g. when using a proxy or non-default port
$baseURL = sprintf('%s://%s%s/?beacon=', $_SERVER['HTTPS'] == 'on' ? 'https' : 'http', $_SERVER['HTTP_HOST'], dirname($_SERVER['REQUEST_URI']));

if(isset($_REQUEST['beacon'])) {
	$qrurl = $baseURL . $_REQUEST['beacon'];
	QRcode::png($qrurl, false, QR_ECLEVEL_L, $pixel_size, $margin);
	exit;
}

if(!isset($_REQUEST['hide']) or $_REQUEST['hide'] != $hide_secret) {
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
