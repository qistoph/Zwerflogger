<?php
include('../config.php');
session_name(Config::$session_name); // Session per instance
session_start();

$db = new SQLite3(Config::$db_file, SQLITE3_OPEN_READWRITE);

function is_logged_in() {
	return isset($_SESSION['teamid']);
}

function logout() {
	// Remove all current session variables and login cookie
	session_unset();

	setcookie(
		/*name:  */ 'teamid',
		/*value: */ null,
		/*expire: */ -1 // Expired
	);
}

function login($teamid, &$error_msg ) {
	global $db;

	logout();

	// Check input format
	if(preg_match('/^[0-9A-F-]{32}$/i', $teamid) === 0) {
		$error_msg = "Invalid TeamID.";
		return false;
	}

	$stmt = $db->prepare('SELECT teamid, name FROM teams WHERE teamid = :teamid');
	$stmt->bindValue(':teamid', $teamid, SQLITE3_TEXT);
	$result = $stmt->execute();
	$team = $result->fetchArray();

	// Check team id is in database
	if($team === FALSE) {
		$error_msg = "Invalid TeamID.";
		return false;
	}

	// Make sure the team id entered matches the one returned from DB (safety check)
	if(strcasecmp($team['teamid'], $teamid)) {
		$error_msg = "Invalid TeamID.";
		return false;
	}

	$stmt = $db->prepare('INSERT INTO logins (team, moment) VALUES(:teamid, CURRENT_TIMESTAMP)');
	$stmt->bindValue(':teamid', $teamid, SQLITE3_TEXT);
	$result = $stmt->execute();
	if($result === false) {
		$error_msg = "Failed to register login.";
		return false;
	}

	if(!setcookie(
		/*name:  */ 'teamid',
		/*value: */ $team['teamid'],
		/*expire: */ time() + 60*60*24*1, // 1 day
		/*path:  */ '', // Use default: current directory
		/*domain: */ '', // Use default: current domain
		/*secure: */ Config::$secure_cookies, // Set to false if hosted on HTTP
		/*httponly: */ true
	)) {
		$error_msg = "Unable to set cookie.";
		return false;
	}

	// Set the session variables
	$_SESSION['teamid'] = $team['teamid'];
	$_SESSION['teamname'] = $team['name'];
	$_SESSION['logintime'] = time();
	return true;
}

function check_beacon($beaconid, &$error_msg) {
	global $db;

	// Checking a beacon requires a valid login
	if(!is_logged_in()) {
		throw new Exception("check_beacon requires logged in team");
	}

	// Check input format
	if(preg_match('/^[0-9A-F-]{32}$/i', $beaconid) === 0) {
		$error_msg = "Invalid beacon";
		return false;
	}
	
	$stmt = $db->prepare('SELECT beaconid, tag FROM beacons WHERE beaconid LIKE :beaconid');
	$stmt->bindValue(':beaconid', $beaconid, SQLITE3_TEXT);
	$result = $stmt->execute();
	$beacon = $result->fetchArray();

	// Check beacon id is in database
	if($beacon === FALSE) {
		$error_msg = "Invalid beacon.";
		return false;
	}

	// Make sure the beacon id entered matches the on returned from DB (safety check)
	if(strcasecmp($beacon['beaconid'], $beaconid)) {
		$error_msg = "Invalid beacon.";
		return false;
	}

	$stmt = $db->prepare('SELECT team, beacon FROM visits WHERE team = :teamid AND beacon LIKE :beaconid');
	$stmt->bindValue(':teamid', $_SESSION['teamid'], SQLITE3_TEXT);
	$stmt->bindValue(':beaconid', $beaconid, SQLITE3_TEXT);
	$result = $stmt->execute();
	$visit = $result->fetchArray();

	// Check if the beacon was already visited by this team
	if($visit !== FALSE) {
		$error_msg = sprintf("You have visited beacon %s already.", $beacon['tag']);
		return false;
	}

	// Register the visit (prefer lower-case storing, though everything should work case insensitive)
	$stmt = $db->prepare('INSERT INTO visits (team, beacon, moment) VALUES(LOWER(:teamid), LOWER(:beaconid), CURRENT_TIMESTAMP)');
	$stmt->bindValue(':teamid', $_SESSION['teamid'], SQLITE3_TEXT);
	$stmt->bindValue(':beaconid', $beaconid, SQLITE3_TEXT);
	$result = $stmt->execute();

	if($result === FALSE) {
		$error_msg = "Visit not record.";
		return false;
	}

	return true;
}

function format_interval(DateInterval $interval) {
	$result = "";
	if ($interval->y) { return $interval->format("%y years ago"); }
	if ($interval->m) { return $interval->format("%m months ago"); }
	if ($interval->d) { return $interval->format("%d days ago"); }
	if ($interval->h) { return $interval->format("%h hours ago"); }
	if ($interval->i) { return $interval->format("%i minutes ago"); }
	if ($interval->s) { return $interval->format("%s seconds ago"); }

	return "now";
}

function get_visits() {
	global $db;

	if(!is_logged_in()) {
		throw new Exception("get_visits requires logged in team");
	}

	$stmt = $db->prepare('SELECT beaconid, tag, moment, score FROM beacons LEFT JOIN visits ON visits.beacon = beacons.beaconid WHERE team = :teamid ORDER BY moment DESC');
	$stmt->bindValue(':teamid', $_SESSION['teamid'], SQLITE3_TEXT);
	$result = $stmt->execute();

	$visits = [];
	while(($visit = $result->fetchArray()) !== FALSE) {
		$visits[] = $visit;
	}

	return $visits;
}

function print_visits() {
	print "<ul>";

	foreach(get_visits() as $visit) {
		$date = date_create_from_format('Y-m-d H:i:s', $visit['moment'], new DateTimeZone("UTC"));
		$age = $date->diff(new DateTime());
		printf("<li>%s (%s) - %d point(s)</li>",
			$visit['tag'],
			format_interval($age),
			$visit['score']);
	}

	print "</ul>";
}

function print_ranking() {
	print "<ol>";

	global $db;

	$stmt = $db->prepare('SELECT name, teamid, IFNULL(SUM(beacons.score), 0) AS score FROM teams LEFT JOIN visits ON teams.teamid = visits.team LEFT JOIN beacons ON beacons.beaconid = visits.beacon GROUP BY teams.name ORDER BY COUNT(*) DESC');
	$result = $stmt->execute();

	while(($rank = $result->fetchArray()) !== FALSE) {
		$class = '';

		if($rank['teamid'] == $_SESSION['teamid']) {
			$class = 'class="myteam"';
		}

		printf("<li %s>%s (%d)</li>", $class, $rank['name'], $rank['score']);
	}

	print "</ol>";
}

if(isset($_GET['teamid'])) {
	$teamid = $_GET['teamid'];
	if(login($teamid, $login_error)) {
		header('Location: '.$_SERVER['DOCUMENT_URI']);
		exit;
	} else {
		print "Login failed: $login_error<br>";
	}
} elseif(!isset($_SESSION['teamid'])) {
	if(isset($_COOKIE['teamid'])) {
		if(!login($_COOKIE['teamid'], $login_error)) {
			print "Could not login from cookie: $login_error<br>";
		}
	}
}

if(isset($_GET['beacon'])) {
	$beaconid = $_GET['beacon'];

	if(!is_logged_in()) {
		print "Login first";
	} else {
		if(!check_beacon($beaconid, $beacon_error)) {
			print "Beacon failed: $beacon_error<br>";
		} else {
			printf("Congratulations. You have visted the beacon, %s<br>", $_SESSION['teamname']);
		}
	}
}

?>
<!doctype html>
<html>
<head>
	<title>CyberZwerftoch - Welcome</title>

	<link rel="stylesheet" href="zwerfstyle.css">
</head>
<body>
<?php
	if(isset($_SESSION['teamid'])) {
		printf("Welcome %s<br>", $_SESSION['teamname']);
?>

<b>Your visits:</b>
<?php print_visits(); ?>

<b>Rankings:</b>
<?php print_ranking(); ?>

<?php
	} else {
?>
<form method="GET">
<?php
		if(isset($_GET['beacon'])) {
			printf("<input type=\"hidden\" name=\"beacon\" value=\"%s\">", htmlspecialchars($_GET['beacon']));
		}
?>
	<label for="teamid">Team ID:</label><input type="text" name="teamid" id="teamid"><br>
	<input type="submit" value="Login">
</form>
<?php
	}
?>
</body>
</html>

<?php
/*
Sample queries:

# Insert beacon 
INSERT INTO beacons VALUES(lower(hex(randomblob(16))), 'First', 1);

# Insert team:
INSERT INTO teams VALUES(lower(hex(randomblob(16))), 'Team Unicorn');

# View visits per team
SELECT name, COUNT(*) FROM teams LEFT JOIN visits ON teams.teamid = visits.team GROUP BY teams.name ORDER BY COUNT(*) DESC;

# View score per team
SELECT name, IFNULL(SUM(beacons.score), 0) FROM teams LEFT JOIN visits ON teams.teamid = visits.team LEFT JOIN beacons ON beacons.beaconid = visits.beacon GROUP BY teams.name ORDER BY COUNT(*) DESC;

# View visits (team, beacon, moment) chronologically
SELECT teams.name, beacons.tag, visits.moment FROM visits LEFT JOIN teams ON teams.teamid = visits.team LEFT JOIN beacons ON beacons.beaconid = visits.beacon ORDER BY visits.moment ASC;

*/
