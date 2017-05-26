<?php
// Requires phpqrcode (Debian: apt-get install phpqrcode)
// http://phpqrcode.sourceforge.net/

include('../config.php');
include(Config::$qrlib_path);

$db = new SQLite3(Config::$db_file, SQLITE3_OPEN_READWRITE);

if(isset($_REQUEST['field']) && isset($_REQUEST['value'])) {
	$qrurl = url_for_field($_REQUEST['field'], $_REQUEST['value']);
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
$stmt = $db->prepare('SELECT teamid, name FROM teams');
$result = $stmt->execute();
while(($team = $result->fetchArray()) !== FALSE) {
	printf('<div class="card"><h1>%s</h1><img src="qr.php?field=teamid&value=%s" title="%s"></div>', $team['name'], $team['teamid'], url_for_field('teamid', $team['teamid']));
}

?>
<h1>Beacons</h1>
<?php
$stmt = $db->prepare('SELECT beaconid, tag FROM beacons');
$result = $stmt->execute();
while(($beacon = $result->fetchArray()) !== FALSE) {
	printf('<div class="card"><h1>Cyber Zwerftocht</h1><img src="qr.php?field=beacon&value=%1$s" title="%2$s"><br>%3$s<br></div>', $beacon['beaconid'], url_for_field('beacon', $beacon['beaconid']), $beacon['tag']);
}
?>

<h1>Registrations</h1>
<table>
<?php
$stmt = $db->prepare('SELECT beaconid, tag FROM beacons');
$result = $stmt->execute();
$beacons = [];
while(($beacon = $result->fetchArray()) !== FALSE) {
	$beacons[] = $beacon;
}

print '<tr>';
print '<th>Team</th>';
print '<th>Starttime</th>';

foreach($beacons as $beacon) {
	printf('<th>%s</th>', $beacon['tag']);
}
print '</tr>';

$stmt = $db->prepare('
SELECT name, teamid, firstLogins.moment AS logintime
FROM teams
LEFT JOIN (
	SELECT loginsL.team, loginsL.moment
	FROM logins loginsL
	LEFT JOIN logins loginsR ON loginsL.team = loginsR.team AND loginsL.moment > loginsR.moment
	WHERE loginsR.team IS NULL
) firstLogins ON firstLogins.team = teams.teamid
GROUP BY teams.name
ORDER BY COUNT(*) DESC;
');
$result = $stmt->execute();
$teams = [];
while(($team = $result->fetchArray()) !== FALSE) {
	$teams[] = $team;
}

foreach($teams as $team) {
	$logintimeStr = '-'; // Default string to show when no login for team
	if($team['logintime'] != '') {
		$logintime = date_create_from_format('Y-m-d H:i:s', $team['logintime'], new DateTimeZone("UTC"));
		$logintime->setTimezone(Config::$time_zone);
		$logintimeStr = $logintime->format(Config::$time_format);
	}
	printf('<tr><td>%s</td><td>%s</td>', $team['name'], $logintimeStr);

	$stmt = $db->prepare('
SELECT beacon, moment
FROM visits
WHERE team = :teamid');
	$stmt->bindValue(':teamid', $team['teamid']);
	$result = $stmt->execute();
	$visits = [];
	while(($visit = $result->fetchArray()) !== FALSE) {
		$visits[] = $visit;
	}

	foreach($beacons as $beacon) {
		$key = array_search($beacon['beaconid'], array_column($visits, 'beacon'));
		if($key === FALSE) {
			print '<td>-</td>';
		} else {
			/* Visited: Yes */
			print '<td>Yes</td>';
			//*/

			/* Show time of visit *
			$visittime = date_create_from_format('Y-m-d H:i:s', $visits[$key]['moment'], new DateTimeZone("UTC"));
			$visittime->setTimezone(Config::$time_zone);
			$visittimeStr = $visittime->format(Config::$time_format);
			printf('<td>%s</td>', $visittimeStr);
			//*/
		}
	}

	print '</tr>';
}

?>
</table>
</body>
</html>
<?php
function url_for_field($field, $value) {
	$qrurl = sprintf('%s?%s=%s', Config::$QR_baseURL, urlencode($field), urlencode($value));
	return $qrurl;
}
