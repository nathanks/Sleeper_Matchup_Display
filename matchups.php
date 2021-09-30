<?php
//--------------------------------------------------------------------------------------------------
//Sleeper Matchup and Standing Display for Dakboard
//--------------------------------------------------------------------------------------------------


//Modify these only
$seasonstart = '2021-09-08';  //the DOTW this date is on will be the DOTW the matchups update
$leagueid = '<LEAGUE_ID_HERE>';


//----------------------------------------------------
// Get Current Week of the Season
//----------------------------------------------------
function get_current_week($seasonstart){

	$today = new DateTime(); // todays date
	$begin = new DateTime($seasonstart); // create a date time
	$i = 1; // first week number
	$end_date = clone $begin;
	$end_date->modify('+17 weeks'); // create the ending week based on the first week of nfl + 17 weeks form that date
	$interval = new DateInterval('P1W'); // interval 1 week
	$range = new DatePeriod($begin, $interval, $end_date);
	$dates = array();
	$found = false;
	foreach($range as $date) {
		if($date >= $today && !$found) { // loop each weeks, if we are inside that week, set it
			$found = true;
			$current_week = $i - 1;
		}
		$dates['Week ' . $i] = $date->format('Y-m-d'); // normal week range filling
		$i++;
	}
	return $current_week;
}


//---------------------------------------------------
// Build SQLite database tables
//---------------------------------------------------
function build_tables($db){

	$db->query('CREATE TABLE IF NOT EXISTS "matchups" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		"roster_id" INTEGER,
		"points" FLOAT,
		"matchup_id" INTEGER
	)');
	
	$db->query('CREATE TABLE IF NOT EXISTS "rosters" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		"roster_id" INTEGER,
		"wins" INTEGER,
		"losses" INTEGER,
		"ties" INTEGER,
		"fpts" FLOAT,
		"owner_id" VARCHAR
	)');
	
	$db->query('CREATE TABLE IF NOT EXISTS "teams" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		"user_id" VARCHAR,
		"team_name" VARCHAR,
		"avatar" VARCHAR,
		"display_name" VARCHAR
	)');

	$db->query('CREATE TABLE IF NOT EXISTS "league" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
		"league_name" VARCHAR
	)');
	
	$statement = $db->prepare('DELETE FROM "teams"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM SQLITE_SEQUENCE WHERE name="teams"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM "rosters"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM SQLITE_SEQUENCE WHERE name="rosters"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM "matchups"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM SQLITE_SEQUENCE WHERE name="matchups"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM "league"');
	$result = $statement->execute();
	$statement = $db->prepare('DELETE FROM SQLITE_SEQUENCE WHERE name="league"');
	$result = $statement->execute();

}

//-------------------------------------------------
// Query Team Info
//-------------------------------------------------
function get_team($db, $owner_id){

	$teamstater = $db->prepare('SELECT * FROM "teams" WHERE user_id = ?');
	$teamstater->bindValue(1, $owner_id);
	$te = $teamstater->execute();
	return $te->fetchArray();
	
}

//-------------------------------------------------
// Query Roster Info
//-------------------------------------------------
function get_roster($db, $roster_id){

	$rosterstate = $db->prepare('SELECT * FROM "rosters" WHERE roster_id = ?');
	$rosterstate->bindValue(1, $roster_id);
	$teroster = $rosterstate->execute();
	return $teroster->fetchArray();

}

//-------------------------------------------------
// Query Team Standings
//-------------------------------------------------
function get_standings($db){
	
	$p=0;
	$standings = array();
	$state = $db->prepare('SELECT t.team_name, r.wins, r.losses FROM teams t JOIN rosters r ON r.owner_id = t.user_id');
	$stand = $state->execute();
	while ($t = $stand->fetchArray()){
		$standings[$p] = $t;
		$p++;
	}
	return $standings;
}

//-------------------------------------------------
// Populate Data Tables
//-------------------------------------------------
function populate_tables($db, $teams, $rosters, $matchups, $league){

	$db->exec('BEGIN');
	foreach ($teams as $team){
		$avatar = (isset($team->metadata->avatar)) ? $team->metadata->avatar : '';
		$db->query('INSERT INTO "teams" ("user_id", "team_name", "avatar", "display_name") 
					VALUES (\'' . $team->user_id . '\', \'' . $team->metadata->team_name . '\', \'' . $avatar . '\', \'' . $team->display_name . '\')');
	}
	$db->exec('COMMIT');

	$db->exec('BEGIN');
		foreach ($rosters as $roster){
			$db->query('INSERT INTO "rosters" ("roster_id", "wins", "losses", "ties", "fpts", "owner_id") 
						VALUES (' . $roster->roster_id . ', ' . $roster->settings->wins . ', ' . $roster->settings->losses . ', ' . $roster->settings->ties . ', ' . $roster->settings->fpts . ', \'' . $roster->owner_id . '\')');
		}
	$db->exec('COMMIT');

	$db->exec('BEGIN');
		foreach ($matchups as $matchup){
			$db->query('INSERT INTO "matchups" ("roster_id", "points", "matchup_id") 
						VALUES (' . $matchup->roster_id . ', ' . $matchup->points . ', ' . $matchup->matchup_id . ')');
		}
	$db->exec('COMMIT');

	$db->exec('BEGIN');
			$db->query('INSERT INTO "league" ("league_name") 
					VALUES (\'' . $league->name . '\')');
	$db->exec('COMMIT');

}

//--------------------------------------------------
// Main Body
//--------------------------------------------------
$db = new SQLite3('football.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
$i=1;
$stand = get_standings($db);
$currentweek = get_current_week($seasonstart);

$urls = array('teams' => 'https://api.sleeper.app/v1/league/' . $leagueid . '/users',
			  'rosters' => 'https://api.sleeper.app/v1/league/' . $leagueid . '/rosters',
			  'matchups' => 'https://api.sleeper.app/v1/league/' . $leagueid . '/matchups/' . $currentweek,
			  'league' => 'https://api.sleeper.app/v1/league/' . $leagueid . '');


foreach ($urls as $key => $val){
	
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, $val);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST,  2);
	$curl_data = curl_exec($curl_handle);
	curl_close($curl_handle);
	
	if ($key == 'teams'){
		$teams = json_decode($curl_data);
	}elseif ($key == 'rosters'){
		$rosters = json_decode($curl_data);
	}elseif ($key == 'matchups'){
		$matchups = json_decode($curl_data);
	}elseif ($key == 'league'){
		$league = json_decode($curl_data);
	}
	

}

$build = build_tables($db);
$populate = populate_tables($db, $teams, $rosters, $matchups, $league);

echo '<html><head><link rel="stylesheet" type="text/css" href="sleeper.css"></head>';

$lestate = $db->prepare('SELECT * FROM "league"');
$lresult = $lestate->execute();
$leagueinfo = $lresult->fetchArray();

$tstatement = $db->prepare('SELECT DISTINCT matchup_id FROM "matchups"');
$tresult = $tstatement->execute();

echo '<h1>' . $leagueinfo['league_name'] . '</h1>';
echo '<table class="zui-table">';

while ($trow = $tresult->fetchArray()) {

	$j=0;
	echo '<tr><th colspan=5>Matchup ' . $i . '</th></tr>';
	
	//get matchup information
	$mteamstate = $db->prepare('SELECT * FROM "matchups" WHERE matchup_id = ?');
	$mteamstate->bindValue(1, $trow['matchup_id']);
	$match = $mteamstate->execute();

	while ($teamer = $match->fetchArray()) {
	
		//get roster info
		$teinfo = get_roster($db, $teamer['roster_id']);
		
		//get team info
		$user = get_team($db, $teinfo['owner_id']);

		if ($j==0){
			echo '<tr>';
			echo '<td>' . $user['team_name'] . '</td><td>' . $teamer['points'] . '</td>';
			echo '<td> vs </td>';
			$j++;
		}else{
			echo '<td>' . $teamer['points'] . '</td><td>' . $user['team_name'] . '</td>';
			echo '</tr>';
		}

	}

	$i++;
	
}

echo '</table>';
echo '<br>';

uasort($stand, function($a, $b){
    return ($a['wins'] < $b['wins']) ? -1 : (($a['wins'] < $b['wins']) ? 1 : 0);
});

echo '<table class="zui-table">';
echo '<tr><th>Team</th><th>Win</th><th>Loss</th></tr>';
foreach ($stand as $stat){
	echo '<tr><td>' . $stat['team_name'] . '</td><td>' . $stat['wins'] . '</td><td>' . $stat['losses'] . '</td></tr>';
}
echo '</table>';

?>