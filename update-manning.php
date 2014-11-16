<?php

date_default_timezone_set('America/Denver');

if ( !class_exists('ftp') ) {
	require('/var/www/lib/class.ftp.php');
}

$ftp_error_display = TRUE;
$ftp_directory_local = '/var/www/vhosts/denverpostplus.com/httpdocs/stats';
$ftp_directory_remote = '';
$ftp_file_format = '';
$ftp_file_mode = FTP_ASCII;

$file_name = 'stats-manning.js';

//function to test http response
function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

// function to return an array of three strings based on the current and past two football seasons, ie '2013-2014'
function get_football_seasons() {
	$currseasonstring = $lastseasonstring = $lastlastseasonstring = '';
	$seasonout[] = array();
	$currentyear = date("Y");
	$currentmonth = date("m");
	
	if ( $currentmonth > 2 ) {
		$seasonout[0] = (string)$currentyear . '-' . (string)($currentyear + 1);
		$seasonout[1] = (string)($currentyear - 1) . '-' . (string)$currentyear;
		$seasonout[2] = (string)($currentyear - 2) . '-' . (string)($currentyear - 1);
	} else {
		$seasonout[0] = (string)($currentyear - 1) . '-' . (string)($currentyear);
		$seasonout[1] = (string)($currentyear - 2) . '-' . (string)($currentyear - 1);
		$seasonout[2] = (string)($currentyear - 3) . '-' . (string)($currentyear - 2);
	}
	return $seasonout;

}

function get_feed_data($feed_year=false, $feed_type='player-stats', $feed_team='21') {
	if ($feed_year) {
		$url_feedbase = 'http://xml.sportsdirectinc.com/sport/v2/football/NFL/';
		$feed_id = str_replace('-','_',$feed_type) . '_' . $feed_team . '_NFL';
		$feedurl = $url_feedbase . $feed_type . '/' . $feed_year . '/' . $feed_id . '.xml';
		if (get_http_response_code($feedurl) != "404") {
			$xml = file_get_contents($feedurl);
		}

		if ($xml) {
			$object = simplexml_load_string($xml);
			return $object;
		} else {
			return false;
		}
	}
}

function get_player_stats($object,$stat_type='stats') {
	$stats = array();
	foreach( $object->{'team-sport-content'}[0]->{'league-content'}[0]->{'season-content'}[0]->{'team-content'}[0]->{'player-content'} as $player ) {
		//17 is Manning's player ID
		if ( substr( $player->{'player'}->{'id'}, -3 ) == ':17' ) {
			if ($stat_type == 'stats') {
				foreach ( $player->{'stat-group'} as $statgroup ) {
					if ( $statgroup->key == 'regular-season-stats' ) {
						foreach ($player->{'stat-group'}->stat as $stat) {
							//var_dump($stat);
							$type = (string)$stat->attributes()->type;
							$number = (string)$stat->attributes()->num;
							$stats["$type"] = $number;
						}
						//file_put_contents($file_name, $stats);
					}
				}
			} elseif ($stat_type == 'player') {
				$firstname = $lastname = '';
				foreach ($player->{'player'}->{'name'} as $names) {
					if ($names->attributes()->type == 'first') { $firstname = (string)$names[0]; }
					if ($names->attributes()->type == 'last') { $lastname = (string)$names[0]; }
				}
				foreach($player->{'player'}->{'season-details'}->{'position'}->{'name'} as $names) {
					$positionshort = ($names->attributes()->type == 'short') ? $names : '';
				}
				$stats = array(
					'name' 		=> $firstname . ' ' . $lastname,
					'height' 	=> (string)$player->{'player'}->height,
					'weight' 	=> (string)$player->{'player'}->weight,
					'born' 		=> (string)$player->{'player'}->{'location'}->{'city'},
					'birthdate' => (string)$player->{'player'}->{'birthdate'},
					'number' 	=> (string)$player->{'player'}->{'season-details'}->{'number'},
					'position' 	=> $positionshort,
					'college' 	=> (string)$player->{'player'}->{'school'}
					);
			}
			return $stats;
		}
	}
}

$seasons = get_football_seasons();

$playerdata = $player_string = false;

$player_stat_string = '<table class="playerstatstable">';
$player_stat_string .= '<tr>';
$player_stat_string .= '<th colspan="3">&nbsp;</th><th colspan="9">Passing</th><th colspan="4">Rushing</th><th colspan="2">Fumbles</th>';
$player_stat_string .= '</tr><tr>';
$player_stat_string .= '<th>Year</th><th>G</th><th>GS</th><th>Comp</th><th>Att</th><th>Comp %</th><th>Yds</th><th>Avg</th><th>Lg</th><th>TD</th><th>Int</th><th>Rate</th><th>Att</th><th>Yds</th><th>Avs</th><th>TD</th><th>Fum</th><th>Lost</th>';
$player_stat_string .= '</tr>';

$final_string = 'var statsOutput=\'';
$final_string_ender = '</table>\'; document.write(statsOutput);';

for($i=0;$i<count($seasons);$i++) {
	//echo "\n" . 'Checking ' . $seasons[$i] . "\n";
	if (!$playerdata) {
		//get player data
		$playerobject = get_feed_data($seasons[$i],'players');
		$playerchunk = get_player_stats($playerobject,'player');
		if (count($playerchunk) > 2) {
			$playerdata = true;
			$remainder = $playerchunk['height'] % 12;
			$number = explode('.',($playerchunk['height'] / 12));
			$height = array($number[0],$remainder);
			$birthdate = date("M d, Y", strtotime($playerchunk['birthdate']));
			$player_string = '<div class="playerdata">';
				$player_string .= '<div class="playertopchunk">';
					$player_string .= '<h2>' . $playerchunk['name'] . ' <span class="number">#' . $playerchunk['number'] . '</span></h2>';
					$player_string .= '<div class="playerhwdiv">';
						$player_string .= '<div class="playerhw player-right"><span class="playeritemlabel">Height</span> ' . $height[0] . '\\\'' . $height[1] . '"</div>';
						$player_string .= '<div class="playerhw player-right"><span class="playeritemlabel">Weight</span> ' . $playerchunk['weight'] . ' lbs.</div>';
						$player_string .= '<div class="clear"></div>';
					$player_string .= '</div>';
					$player_string .= '<div class="clear"></div>';
				$player_string .= '</div>';
				$player_string .= '<div class="playerhomediv">';
					$player_string .= '<div class="playerhome player-left"><span class="playeritemlabel">From</span> ' . $playerchunk['born'] . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Born</span> ' . $birthdate . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Plays</span> ' . $playerchunk['position'] . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">School</span> ' . $playerchunk['college'] . '</div>';
					$player_string .= '<div class="playerhome player-right"><a href="http://stats.denverpost.com/football/nfl-players.aspx?page=/data/nfl/players/player17.html" title="Peyton Manning full statistics">Full Stats &raquo;</a></div>';
					$player_string .= '<div class="clear"></div>';
				$player_string .= '</div>';
			$player_string .= '</div>';
			$final_string .= $player_string;
		}
	}
	//get player stats
	$statsobject = get_feed_data($seasons[$i]);
	$chunk = get_player_stats($statsobject,'stats');
	$games_played = (isset($chunk['games_played']) ? $chunk['games_played'] : '0');
	$games_started = (isset($chunk['games_started']) ? $chunk['games_started'] : '0');
	$passing_plays_completed = (isset($chunk['passing_plays_completed']) ? $chunk['passing_plays_completed'] : '0');
	$passing_plays_attempted = (isset($chunk['passing_plays_attempted']) ? $chunk['passing_plays_attempted'] : '0');
	$passing_yards = (isset($chunk['passing_yards']) ? $chunk['passing_yards'] : '0');
	$passing_longest_yards = (isset($chunk['passing_longest_yards']) ? $chunk['passing_longest_yards'] : '0');
	$passing_touchdowns = (isset($chunk['passing_touchdowns']) ? $chunk['passing_touchdowns'] : '0');
	$passing_plays_intercepted = (isset($chunk['passing_plays_intercepted']) ? $chunk['passing_plays_intercepted'] : '0');
	$passer_rating = (isset($chunk['passer_rating']) ? round($chunk['passer_rating'],1) : '0');
	$rushing_plays = (isset($chunk['rushing_plays']) ? $chunk['rushing_plays'] : '0');
	$rushing_net_yards = (isset($chunk['rushing_net_yards']) ? $chunk['rushing_net_yards'] : '0');
	$rushing_touchdowns = (isset($chunk['rushing_touchdowns']) ? $chunk['rushing_touchdowns'] : '0');
	$fumbles = (isset($chunk['fumbles']) ? $chunk['fumbles'] : '0');
	$fumbles_lost = (isset($chunk['fumbles_lost']) ? $chunk['fumbles_lost'] : '0');
	$pass_completion = ( $passing_plays_completed > 0 && $passing_plays_attempted > 0 ? round( ($passing_plays_completed / $passing_plays_attempted), 1) : '-');
	$pass_yds_avg = ( $passing_yards > 0 && $passing_plays_attempted > 0 ? round( ($passing_yards / $passing_plays_attempted), 1) : '-');
	$rush_avg_yds = ( $rushing_net_yards > 0 && $rushing_plays > 0 ? round( ($rushing_net_yards / $rushing_plays), 1) : '0.0');

	$player_stat_string .= '<tr>';
		$player_stat_string .= '<td>' . $seasons[$i] . '</td>';
		$player_stat_string .= '<td>' . $games_played . '</td>';
		$player_stat_string .= '<td>' . $games_started . '</td>';
		$player_stat_string .= '<td>' . $passing_plays_completed . '</td>';
		$player_stat_string .= '<td>' . $passing_plays_attempted . '</td>';
		$player_stat_string .= '<td>' . $pass_completion . '</td>';
		$player_stat_string .= '<td>' . $passing_yards . '</td>';
		$player_stat_string .= '<td>' . $pass_yds_avg . '</td>';
		$player_stat_string .= '<td>' . $passing_longest_yards . '</td>';
		$player_stat_string .= '<td>' . $passing_touchdowns . '</td>';
		$player_stat_string .= '<td>' . $passing_plays_intercepted . '</td>';
		$player_stat_string .= '<td>' . $passer_rating . '</td>';
		$player_stat_string .= '<td>' . $rushing_plays . '</td>';
		$player_stat_string .= '<td>' . $rushing_net_yards . '</td>';
		$player_stat_string .= '<td>' . $rush_avg_yds . '</td>';
		$player_stat_string .= '<td>' . $rushing_touchdowns . '</td>';
		$player_stat_string .= '<td>' . $fumbles . '</td>';
		$player_stat_string .= '<td>' . $fumbles_lost . '</td>';
	$player_stat_string .= '</tr>';
}

$final_string .= $player_stat_string . $final_string_ender;


file_put_contents($file_name, $final_string);

$ftp = new ftp();
$ftp->connection_passive();
$ftp->file_put($file_name, $ftp_directory_local, $ftp_file_format, $ftp_error_display, $ftp_file_mode, $ftp_directory_remote);
$ftp->ftp_connection_close();

?>