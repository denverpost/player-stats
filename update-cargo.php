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

$file_name = 'stats-cargo.js';

//function to test http response
function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

// function to return an array of three strings based on the current and past two baseball seasons (years)
function get_baseball_seasons() {
	$currseasonstring = $lastseasonstring = $lastlastseasonstring = '';
	$currentyear = date("Y");
	$seasonout = array($currentyear + 0,$currentyear - 1, $currentyear - 2);
	return $seasonout;
}

function get_feed_data($feed_year=false, $feed_type='player-stats', $feed_team='2956') {
	if ($feed_year) {
		$url_feedbase = 'http://xml.sportsdirectinc.com/sport/v2/baseball/MLB/';
		$feed_id = str_replace('-','_',$feed_type) . '_' . $feed_team . '_MLB';
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
		//43449 is Cargo's player ID
		if ( substr( $player->{'player'}->{'id'}, -6 ) == ':43449' ) {
			if ($stat_type == 'stats') {
				foreach ( $player->{'stat-group'} as $statgroup ) {
					if ( $statgroup->key == 'regular-season-stats' ) {
						foreach ($statgroup->stat as $stat) {
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
					'birthdate' => (string)$player->{'player'}->{'birthdate'},
					'number' 	=> (string)$player->{'player'}->{'season-details'}->{'number'},
					'position' 	=> $positionshort,
					'throws' 	=> ucfirst((string)$player->{'player'}->{'throws'}),
					'hits'	 	=> ucfirst((string)$player->{'player'}->{'bats'})
					);
			}
			return $stats;
		}
	}
}

$seasons = get_baseball_seasons();

$playerdata = $player_string = false;

$player_stat_string = '<table class="playerstatstable">';
$player_stat_string .= '<tr>';
$player_stat_string .= '<th colspan="17">Batting Statistics</th>';
$player_stat_string .= '</tr><tr>';
$player_stat_string .= '<th>Year</th><th>G</th><th>AB</th><th>R</th><th>H</th><th>2B</th><th>3B</th><th>HR</th><th>RBI</th><th>SB</th><th>CS</th><th>BB</th><th>K</th><th>SAC</th><th>Avg.</th><th>OBP</th><th>SLG</th>';
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
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Born</span> ' . $birthdate . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Plays</span> ' . $playerchunk['position'] . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Throws</span> ' . $playerchunk['throws'] . '</div>';
					$player_string .= '<div class="playerhome"><span class="playeritemlabel">Hits</span> ' . $playerchunk['hits'] . '</div>';
					$player_string .= '<div class="playerhome player-right"><a href="http://stats.denverpost.com/baseball/mlb-players.aspx?page=/data/mlb/players/player43449.html" title="Carlos Gonzalez full statistics">Full Stats &raquo;</a></div>';
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
	$at_bats = (isset($chunk['at_bats']) ? $chunk['at_bats'] : '0');
	$runs = (isset($chunk['runs']) ? $chunk['runs'] : '0');
	$hits = (isset($chunk['hits']) ? $chunk['hits'] : '0');
	$doubles = (isset($chunk['doubles']) ? $chunk['doubles'] : '0');
	$triples = (isset($chunk['triples']) ? $chunk['triples'] : '0');
	$home_runs = (isset($chunk['home_runs']) ? $chunk['home_runs'] : '0');
	$runs_batted_in = (isset($chunk['runs_batted_in']) ? $chunk['runs_batted_in'] : '0');
	$stolen_bases = (isset($chunk['stolen_bases']) ? round($chunk['stolen_bases'],1) : '0');
	$caught_stealing = (isset($chunk['caught_stealing']) ? $chunk['caught_stealing'] : '0');
	$walks = (isset($chunk['walks']) ? $chunk['walks'] : '0');
	$strikeouts = (isset($chunk['strikeouts']) ? $chunk['strikeouts'] : '0');
	$sacrifice_hits = (isset($chunk['sacrifice_hits']) ? $chunk['sacrifice_hits'] : '0');
	$fumbles_lost = (isset($chunk['fumbles_lost']) ? $chunk['fumbles_lost'] : '0');
	$batting_average = ( (isset($chunk['batting_average']) && $chunk['batting_average'] > 0) ? round($chunk['batting_average'], 3) : '-');
	$on_base_percentage = ( (isset($chunk['on_base_percentage']) && $chunk['on_base_percentage'] > 0) ? round( $chunk['on_base_percentage'], 3) : '-');
	$slugging_percentage = ( (isset($chunk['slugging_percentage']) && $chunk['slugging_percentage'] > 0) ? round( $chunk['slugging_percentage'], 3) : '-');

	$player_stat_string .= '<tr>';
		$player_stat_string .= '<td>' . $seasons[$i] . '</td>';
		$player_stat_string .= '<td>' . $games_played . '</td>';
		$player_stat_string .= '<td>' . $at_bats . '</td>';
		$player_stat_string .= '<td>' . $runs . '</td>';
		$player_stat_string .= '<td>' . $hits . '</td>';
		$player_stat_string .= '<td>' . $doubles . '</td>';
		$player_stat_string .= '<td>' . $triples . '</td>';
		$player_stat_string .= '<td>' . $home_runs . '</td>';
		$player_stat_string .= '<td>' . $runs_batted_in . '</td>';
		$player_stat_string .= '<td>' . $stolen_bases . '</td>';
		$player_stat_string .= '<td>' . $caught_stealing . '</td>';
		$player_stat_string .= '<td>' . $walks . '</td>';
		$player_stat_string .= '<td>' . $strikeouts . '</td>';
		$player_stat_string .= '<td>' . $sacrifice_hits . '</td>';
		$player_stat_string .= '<td>' . $batting_average . '</td>';
		$player_stat_string .= '<td>' . $on_base_percentage . '</td>';
		$player_stat_string .= '<td>' . $slugging_percentage . '</td>';
	$player_stat_string .= '</tr>';
}

$final_string .= $player_stat_string . $final_string_ender;

file_put_contents($file_name, $final_string);

$ftp = new ftp();
$ftp->connection_passive();
$ftp->file_put($file_name, $ftp_directory_local, $ftp_file_format, $ftp_error_display, $ftp_file_mode, $ftp_directory_remote);
$ftp->ftp_connection_close();

?>