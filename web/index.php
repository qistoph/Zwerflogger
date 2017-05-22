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

function check_beacon($beaconid, &$beacon_tag, &$error_msg) {
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

	$beacon_tag = $beacon['tag'];

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

function get_progress() {
	global $db;

	if(!is_logged_in()) {
		throw new Exception("get_progress requires logged in team");
	}

	$stmt = $db->prepare('SELECT CAST(COUNT(team) AS FLOAT) / COUNT(*) AS progress FROM beacons LEFT JOIN visits ON visits.beacon = beacons.beaconid WHERE visits.team = :teamid OR visits.beacon IS NULL;');
	$stmt->bindValue(':teamid', $_SESSION['teamid'], SQLITE3_TEXT);
	$result = $stmt->execute();

	return $result->fetchArray()[0];
}

function print_visits() {
	print '<table class="table table-striped table-hover">
				<tr>
					<th>Tag</th>
					<th>Time</th>
					<th>Score</th>
				</tr>';


	foreach(get_visits() as $visit) {
		$date = date_create_from_format('Y-m-d H:i:s', $visit['moment'], new DateTimeZone("UTC"));
		$age = $date->diff(new DateTime());
		printf('<tr><td><i class="glyphicon glyphicon-map-marker" aria-hidden="true"></i> %s</td><td>%s</td><td>%d</td></tr>',
			$visit['tag'],
			format_interval($age),
			$visit['score']);
	}

	print "</table>";
}

function print_ranking() {
	global $db;

	$stmt = $db->prepare('
		SELECT name, teamid, IFNULL(SUM(beacons.score), 0) AS score, firstLogins.moment AS logintime, lastVisits.tag AS lastbeacon
		FROM teams
		LEFT JOIN visits ON teams.teamid = visits.team
		LEFT JOIN beacons ON beacons.beaconid = visits.beacon
		LEFT JOIN (
		  SELECT loginsL.team, loginsL.moment
		  FROM logins loginsL
		  LEFT JOIN logins loginsR
			ON loginsL.team = loginsR.team
			AND loginsL.moment > loginsR.moment
		  WHERE loginsR.team IS NULL
		) firstLogins ON firstLogins.team = teams.teamid
		LEFT JOIN (
		  SELECT visitsL.team, beacons.tag
		  FROM visits visitsL
		  LEFT JOIN visits visitsR
			ON visitsL.team = visitsR.team
			AND visitsL.moment < visitsR.moment
		  LEFT JOIN beacons
			ON visitsL.beacon = beacons.beaconid
		  WHERE visitsR.team is NULL
		) lastVisits ON lastVisits.team = teams.teamid
		GROUP BY teams.name
		ORDER BY COUNT(*) DESC;');
	$result = $stmt->execute();

	print '<table class="table table-striped table-hover">
				<tr>
					<th>Rank</th>
					<th>Team</th>
					<th>Score</th>
					<th>Start time</th>
					<th>Last beacon</th>
				</tr>';

	$rankNr = 1;
	while(($rank = $result->fetchArray()) !== FALSE) {
		$class = '';

		if(isset($_SESSION['teamid']) && $rank['teamid'] == $_SESSION['teamid']) {
			$class = 'class="myteam info"';
		}

		printf("<tr %s><td>%d.</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>", $class, $rankNr, $rank['name'], $rank['score'], $rank['logintime'], $rank['lastbeacon']);

		$rankNr++;
	}

	print '</table>';
}

if(isset($_GET['action'])) {
	if($_GET['action'] == 'logout') {
		logout();
	}
} elseif(isset($_GET['teamid'])) {
	$teamid = $_GET['teamid'];
	if(login($teamid, $login_error)) {
		header('Location: '.explode('?', $_SERVER['REQUEST_URI'])[0]);
		exit;
	}
} elseif(!isset($_SESSION['teamid'])) {
	if(isset($_COOKIE['teamid'])) {
		if(!login($_COOKIE['teamid'], $login_error)) {
			$login_error = "Could not login from cookie - $login_error";
		}
	}
}

if(isset($_GET['beacon'])) {
	if(!is_logged_in()) {
		$beacon_error = "Login required";
	} else {
		if(check_beacon($_GET['beacon'], $beacon_tag, $beacon_error)) {
			$beacon_visited = $beacon_tag;
		}
	}
}

?>
<!doctype html>
<html>
<head lang="en">
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

	<!-- Optional theme -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">

	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>

	<title>Cyberzwerftocht 2017</title>

	<link rel="stylesheet" href="zwerfstyle.css">
</head>
<body>
	<nav class="navbar navbar-default">
		<div class="container">
			<div class="navbar-brand">
				Cyberzwerftocht 2017
			</div>
<?php
if(is_logged_in()) {
	print '<form method="GET"><button type="submit" class="btn btn-default navbar-btn navbar-right" name="action" value="logout"><i class="glyphicon glyphicon-log-out"></i></button></form>';
}
?>
		</div>
	</nav>

	<div class="container" align="center">

<?php
if(isset($login_error)) {
	printf('<div class="alert alert-warning"><i class="glyphicon glyphicon-exclamation-sign"></i> Login failed: %s</div>', $login_error);
}

if(isset($beacon_error)) {
	printf('<div class="alert alert-danger"><i class="glyphicon glyphicon-remove-sign"></i> Beacon visit registration failed:<br>%s</div>', $beacon_error);
}

if(isset($beacon_visited)) {
	printf('<div class="alert alert-success"><i class="glyphicon glyphicon-ok-sign"></i> Congratualations, you have visited the beacon <b>%s</b></div>', $beacon_visited);
}
?>
		<div class="row">
			<b>Rankings:</b>
			<?php print_ranking(); ?>
		</div>

<?php
if(isset($_SESSION['teamid'])) {
?>
		<div class="row">
<?php
		printf("Welcome %s<br>", $_SESSION['teamname']);
		printf('
		<div class="progress" style="width: 60%%">
			<div class="progress-bar" role="progressbar" style="min-width: 2em; width: %1$d%%">%1$d%%</div>
		</div>', get_progress() * 100);
?>

			<b>Your visits:</b>
			<?php print_visits(); ?>
		</div>
<?php
	} else {
?>
		<div class="row">
			<form method="GET" class="form-inline">
<?php
		if(isset($_GET['beacon'])) {
			printf("<input type=\"hidden\" name=\"beacon\" value=\"%s\">", htmlspecialchars($_GET['beacon']));
		}
?>
				<div class="input-group">
					<span class="input-group-addon" id="basic-addon1"><i class="glyphicon glyphicon-qrcode" aria-hidden="true"></i></span>
					<input type="text" class="form-control" name="teamid" placeholder="Team ID">
				</div>
				<button type="submit" class="btn btn-primary">Login</button>
			</form>
		</div>
<?php
	}
?>
	</div>
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

# Ranking with first login from logins
SELECT name, teamid, IFNULL(SUM(beacons.score), 0) AS score, firstLogins.moment
FROM teams
LEFT JOIN visits ON teams.teamid = visits.team
LEFT JOIN beacons ON beacons.beaconid = visits.beacon
LEFT JOIN (
  SELECT loginsL.team, loginsL.moment
  FROM logins loginsL
  LEFT JOIN logins loginsR ON loginsL.team = loginsR.team AND loginsL.moment > loginsR.moment
  WHERE loginsR.team IS NULL
) firstLogins ON firstLogins.team = teams.teamid
GROUP BY teams.name
ORDER BY COUNT(*) DESC;

# Ranking with first login since moment X
SELECT name, teamid, IFNULL(SUM(beacons.score), 0) AS score, firstLogins.moment
FROM teams
LEFT JOIN visits ON teams.teamid = visits.team
LEFT JOIN beacons ON beacons.beaconid = visits.beacon
LEFT JOIN (
  SELECT loginsL.team, loginsL.moment
  FROM logins loginsL
  LEFT JOIN logins loginsR
    ON loginsL.team = loginsR.team
    AND loginsL.moment > loginsR.moment
    AND loginsR.moment > '2017-05-19 13:59'
  WHERE loginsR.team IS NULL
  AND loginsL.moment > '2017-05-19 13:59'
) firstLogins ON firstLogins.team = teams.teamid
GROUP BY teams.name
ORDER BY COUNT(*) DESC;

# Ranking with first login and last beacon
SELECT name, teamid, IFNULL(SUM(beacons.score), 0) AS score, firstLogins.moment, lastVisits.tag
FROM teams
LEFT JOIN visits ON teams.teamid = visits.team
LEFT JOIN beacons ON beacons.beaconid = visits.beacon
LEFT JOIN (
  SELECT loginsL.team, loginsL.moment
  FROM logins loginsL
  LEFT JOIN logins loginsR
    ON loginsL.team = loginsR.team
    AND loginsL.moment > loginsR.moment
  WHERE loginsR.team IS NULL
) firstLogins ON firstLogins.team = teams.teamid
LEFT JOIN (
  SELECT visitsL.team, beacons.tag
  FROM visits visitsL
  LEFT JOIN visits visitsR
    ON visitsL.team = visitsR.team
    AND visitsL.moment < visitsR.moment
  LEFT JOIN beacons
    ON visitsL.beacon = beacons.beaconid
  WHERE visitsR.team is NULL
) lastVisits ON lastVisits.team = teams.teamid
GROUP BY teams.name
ORDER BY COUNT(*) DESC;

*/
