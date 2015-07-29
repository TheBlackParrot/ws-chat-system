<?php
namespace chat_controller;

if(php_sapi_name() != "cli") {
	die("CLI-only");
}

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

$root = dirname(dirname(dirname(dirname(__FILE__))));
include "$root/includes/db.php";

function logController($cmd,$value) {
	echo "[" . date('m/d/Y H:i:s') . "] :: [" . $cmd . "] -- " . $value . "\r\n";
}

class ChatController implements MessageComponentInterface {
	protected $clients;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onOpen(ConnectionInterface $conn) {
		// Store the new connection to send messages to later
		$this->clients->attach($conn);

		logController("ConnectionInterface", "New connection from {$conn->remoteAddress} (ID {$conn->resourceId})");
	}

	public function onMessage(ConnectionInterface $from, $msg) {
		$return_data = handleControllerData($msg, $from);

		/*if($return_data != NULL && $return_data != "") {
			$index = count($return_data) - 2;
			$back_mode = $return_data[$index];
			unset($return_data[$index]);

			switch($back_mode) {
				case 3:
					// send data back to all clients
					foreach ($this->clients as $client) {
						$client->send(json_encode($return_data,JSON_FORCE_OBJECT));
					}

					break;

				case 2:
					// send data back to all except sending client
					foreach ($this->clients as $client) {
						if($from !== $client) {
							$client->send(json_encode($return_data,JSON_FORCE_OBJECT));
						}
					}
					
					break;

				case 1:
					// send data back to sending client
					$from->send(json_encode($return_data,JSON_FORCE_OBJECT));
					break;

				case 0:
				default:
					// internal only, send nothing back
					break;
			}
		}*/
	}

	public function onClose(ConnectionInterface $conn) {
		global $user;
		global $room;

		foreach ($user as $uuid => $user_data) {
			if(isset($user[$uuid]['conn'])) {
				if($user[$uuid]['conn'] === $conn) {
					if(!isset($user[$uuid]['room'])) {
						$this->clients->detach($conn);
						logController("ConnectionInterface", "Connection {$conn->resourceId} has disconnected");
						return;
					}
					$found_uuid = $uuid;
					break;
				}
			}
		}

		if(isset($found_uuid)) {
			$arr = [];
			$arr['uuid'] = $found_uuid;
			$arr['silent'] = 1;

			$room_n = $user[$found_uuid]['room'];
			foreach($room[$room_n]['users'] as $uuid => $user_data) {
				$user[$uuid]['conn']->send(formatResponse($arr, "userLeave"));
			}

			if(isset($room[$room_n]['users'][$found_uuid])) {
				unset($room[$room_n]['users'][$found_uuid]);
				if(count($room[$room_n]['users']) < 1 && !$room[$room_n]['persistent']) {
					unset($room[$room_n]);
				}
			}
			if(isset($user[$found_uuid])) {
				unset($user[$found_uuid]);
			}
		}

		$this->clients->detach($conn);
		logController("ConnectionInterface", "Connection {$conn->resourceId} has disconnected");
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";

		$conn->close();
	}
}

date_default_timezone_set("America/Chicago");

//$vlc['paused'] = 0;

// seriously
//$GLOBALS['mpd']['paused'] = 0;
//$GLOBALS['mpd']['title'] = "";

$guest_number = 1;
$room = [];
// temp
$user = [];
$GLOBALS['user'] = $user;
$GLOBALS['guest_number'] = $guest_number;

function formatResponse($arr, $type) {
	$arr['type'] = $type;
	return json_encode($arr, JSON_FORCE_OBJECT);
}
function clientError($conn, $msg) {
	$conn->send(formatResponse(array($msg),"ClientError"));
}

function getGuestName() {
	global $guest_number;

	$num = $guest_number;
	$guest_number++;

	return "Guest$num";
}

// thing for /unban
function stripBanEnds($n) {
	echo("Changed $n to '" . substr($n, 0, stripos($n, "^")) . "'\r\n");
	return substr($n, 0, stripos($n, "^"));
}

include "$root/includes/parsedown.php";

$parsedown = new Parsedown();
$parsedown->setBreaksEnabled(true);
$parsedown->setMarkupEscaped(true);
$GLOBALS['parsedown'] = $parsedown;
$root = dirname(dirname(dirname(dirname(__FILE__))));
$GLOBALS['root'] = $root;

$results = $database['rooms']->query("SELECT * FROM rooms");
while($row = $results->fetchArray()) {
	$room[$row['NAME']] = [];
	$room[$row['NAME']]['users'] = [];
	$room[$row['NAME']]['NSFW'] = $row['NSFW'];
	$room[$row['NAME']]['persistent'] = 1;

	$room[$row['NAME']]['bans'] = [];
	$bans = explode(";", $row['BANS']);
	if($bans[0] != "") {
		foreach ($bans as $ban) {
			$details = [];
			$details = explode("^", $ban);
			$room[$row['NAME']]['bans'][$details[0]] = [];
			$room[$row['NAME']]['bans'][$details[0]]['ends'] = $details[1];
			$room[$row['NAME']]['bans'][$details[0]]['username'] = $details[2];
			logController("room/{$row['NAME']}", "Setting ban for {$details[2]} ({$details[0]}), ends {$details[1]}");
		}
	}

	logController("core", "Created persistent room {$row['NAME']}");
}
$GLOBALS['room'] = $room;

function handleControllerData($msg, $conn) {
	global $root;

	$data = explode("\t",$msg);
	$data = array_map('htmlspecialchars', $data);

	include "$root/includes/colors.php";

	global $room;
	global $user;
	global $guest_number;
	global $database;

	// messages will be similar to "core\tgetCode\targ1"

	switch($data[0]) {
		case "p":
			$conn->send("p");
			break;

		case "core":
			switch($data[1]) {
				case "preventChromeFromBeingStupid":
					$arr = [];
					$arr['ping'] = 1;
					$conn->send(formatResponse($arr, "userActuallyOpenedThePage"));
					break;

				case "checkNSFW":
					if(!isset($data[2])) {
						clientError($conn, "No room defined to check for NSFW content.");
						return;
					}
					$room_n = strtolower($data[2]);

					$arr['nsfw'] = isset($room[$room_n]) ? $room[$room_n]['NSFW'] : 0;

					$conn->send(formatResponse($arr, "checkNSFW"));
					break;

				case "checkDetails":
					$arr = [];

					if(!isset($data[2])) {
						clientError($conn, "You have not defined a room to check.");
						return;
					}
					$data[2] = strtolower($data[2]);

					$allowed_chars = array(" ", ".", "_");

					$arr['room_exists'] = 0;
					$safe = str_replace($allowed_chars, '', $data[2]);
					if(!ctype_alnum($safe) || strlen($data[2]) > 32) {
						clientError($conn, "Your room is invalid. Only alphanumeric characters, spaces, periods, and underscores are allowed. Usernames must be less than 32 characters. (tested \"$safe\")");
						return;
					}

					if(isset($room[$data[2]])) {
						$arr['room_exists'] = 1;
					}

					if(count($room[$data[2]]['bans']) > 0) {
						$IP = $conn->remoteAddress;
						if(array_key_exists($IP, $room[$data[2]]['bans'])) {
							$ends = $room[$data[2]]['bans'][$IP]['ends'];
							if(time() < $ends) {
								$arr['msg'] = "You are currently banned from this room.";
								$arr['ends'] = $ends;
								$conn->send(formatResponse($arr, "banned"));
								return;
							} else {
								if($room[$data[2]]['persistent']) {
									$bans_str = $database['rooms']->querySingle('SELECT BANS FROM rooms WHERE NAME="' . $data[2] . '"');
									$bans = explode(";", $bans_str);

									//PHP doesn't like this, dunno why
									//$IP_list = array_map("stripBanEnds", $bans);

									$IP_list = [];
									for($i = 0; $i < count($bans); $i++) {
										if($bans[$i] != "") {
											$IP_list[] = stripBanEnds($bans[$i]);
										} else {
											unset($bans[$i]);
										}
									}
									$bans = array_values($bans);

									$key = array_search($IP, $IP_list);

									unset($bans[array_search($IP, $IP_list)]);
									$bans = array_values($bans);

									$new_bans = implode(";", $bans);
									$database['rooms']->exec('UPDATE rooms SET BANS="' . $new_bans . '" WHERE NAME="' . $data[2] . '"');
								}

								unset($room[$data[2]]['bans'][$IP]);
							}
						}
					}

					$reserved_names = array(
						"admin",
						"administrator",
						"moderator",
						"mod",
						"owner",
						"host",
						"system",
						"guest"
					);

					$safe = str_replace($allowed_chars, '', $data[3]);
					if(!ctype_alnum($safe)) {
						if($data[3] != "") {
							clientError($conn, "Your username is invalid. Only alphanumeric characters, spaces, periods, and underscores are allowed.");
							return;
						}
					} else {
						$nums = range(0, 9);
						$safer = strtolower(str_replace($nums, "", $safe));

						if(in_array($safer, $reserved_names)) {
							clientError($conn, "This username is reserved by the system.");
							return;
						}
						if($arr['room_exists']) {
							foreach ($room[$data[2]]['users'] as $uuid => $user_data) {
								if(strtolower($user[$uuid]['name']) == strtolower($data[3])) {
									clientError($conn, "This username is already taken.");
									return;
								}
							}
						}

						$user_exists = 0;
						$query = "SELECT * FROM users WHERE USERNAME=\"{$data[3]}\" COLLATE NOCASE";
						$results = $database['users']->query($query);
						while($row = $results->fetchArray()) {
							$user_exists = 1;
							$hash = trim($row['HASH']);
						}

						if($user_exists) {
							if($data[4] == "") {
								clientError($conn, "This username is registered.");
								return;
							} else {
								// $data[4] can be anything here, it's just a check to see if the password field is present
								// $pass_key is sent back to be rehashed with the password hash
								$pass_key = generateKey(8, 16);
								$expected_final = hash('sha256', $hash . trim($pass_key));

								$database['users']->exec('UPDATE users SET VERIFY_HASH="' . $expected_final . '" WHERE USERNAME="' . $data[3] . '"');
							}
						}

						$str = generateKey(15, 30);
						while(array_key_exists($str, $user)) {
							$str = generateKey(15, 30);
						}
						
						$arr['key'] = $str;
						$arr['name'] = $data[3];
						$arr['room'] = $data[2];
						$arr['pass_key'] = (isset($pass_key)) ? $pass_key : "";

						// create a temporary user to say that they have in fact verified
						$user[$str]['verified'] = 1;
						$user[$str]['name'] = $data[3];
						$user[$str]['room'] = $data[2];
						$user[$str]['pass_key'] = $arr['pass_key'];

						$conn->send(formatResponse($arr, "verified"));
					}
					break;

				case "checkPasswordHash":
					if(!isset($data[2])) {
						clientError($conn, "You have not verified with the chat server.");
						return;
					} else {
						if(!isset($user[$data[2]])) {
							clientError($conn, "Invalid initial verification key.");
							return;
						}
					}

					if(!isset($data[3])) {
						clientError($conn, "Did not receive a password hash.");
						return;
					}

					$query = 'SELECT * FROM users WHERE USERNAME="' . $user[$data[2]]['name'] . '"';
					$results = $database['users']->query($query);
					$row = $results->fetchArray();

					$database['users']->exec('UPDATE users SET VERIFY_HASH="" WHERE USERNAME="' . $user[$data[2]]['name'] . '"');
					//echo("SENT HASH: {$data[3]}\r\nEXPECTED: {$row['VERIFY_HASH']}\r\nSTORED: {$row['HASH']}");
					if($data[3] == $row['VERIFY_HASH']) {
						$user[$data[2]]['pass_key'] = generateKey(15, 30);
						$database['users']->exec('UPDATE users SET VERIFY_HASH="' . $user[$data[2]]['pass_key'] . '" WHERE USERNAME="' . $user[$data[2]]['name'] . '"');
					} else {
						clientError($conn, "Invalid password.");
						return;
					}

					$arr['pass_key'] = $user[$data[2]]['pass_key'];
					$arr['key'] = $data[2];
					$conn->send(formatResponse($arr, "passwordVerified"));
					break;


				case "requestUUID":
					if(!isset($data[2])) {
						clientError($conn, "You have not verified with the chat server.");
						return;
					} else {
						if(!isset($user[$data[2]])) {
							clientError($conn, "Invalid initial verification key.");
							return;
						}
					}

					$user_exists = 0;
					$query = 'SELECT * FROM users WHERE USERNAME="' . $user[$data[2]]['name'] . '"';
					$results = $database['users']->query($query);
					while($row = $results->fetchArray()) {
						$user_exists = 1;
						$pass_key = $row['VERIFY_HASH'];
					}

					if($user_exists) {
						$database['users']->exec('UPDATE users SET VERIFY_HASH="" WHERE USERNAME="' . $user[$data[2]]['name'] . '"');
						if(!isset($data[3])) {
							clientError($conn, "Password hasn't been verified by the chat server.");
							return;
						}
						if($data[3] == "") {
							clientError($conn, "Password hasn't been verified by the chat server.");
							return;
						}
						if($data[3] != $pass_key) {
							clientError($conn, "Invalid password key.");
							return;
						}
					}

					$arr = [];
					$arr['uuid'] = uniqid();
					while(array_key_exists($arr['uuid'], $user)) {
						$arr['uuid'] = uniqid();
					}

					$user[$arr['uuid']] = [];
					$user[$arr['uuid']]['timestamp'] = time();
					$user[$arr['uuid']]['name'] = $arr['name'] = (($user[$data[2]]['name'] != "") ? $user[$data[2]]['name'] : getGuestName());
					$user[$arr['uuid']]['color'] = explode(",", $colors[array_rand($colors)]);
					$user[$arr['uuid']]['conn'] = $conn;
					$user[$arr['uuid']]['ignored'] = [];

					$user[$arr['uuid']]['verification'] = $arr['verification'] = generateKey(40, 60);

					$room_n = strtolower($user[$data[2]]['room']);
					$user[$arr['uuid']]['room'] = $room_n;

					$user_rank = "user";
					if(!isset($room[$room_n])) {
						logController("core/requestUUID", $user[$arr['uuid']]['name'] . " created room " . $user[$data[2]]['room']);

						$room[$room_n]['users'][$arr['uuid']]['connection'] = $conn;
						$user_rank = "owner";
						$room[$room_n]['users'][$arr['uuid']]['rank'] = $user_rank;

						$room[$room_n]['NSFW'] = 0;
						$room[$room_n]['persistent'] = 0;
						$room[$room_n]['bans'] = [];
					} else {
						$is_persistent = 0;
						$query = 'SELECT * FROM rooms WHERE NAME="' . $room_n . '"';
						$results = $database['rooms']->query($query);
						while($row = $results->fetchArray()) {
							$is_persistent = 1;
							$found_room = $row;
						}

						if($is_persistent) {
							if($user[$arr['uuid']]['name'] == $found_room['CREATOR']) {
								$user_rank = "owner";
							}

							$mods = explode(";", $found_room['MODS']);
							if(in_array($user[$arr['uuid']]['name'], $mods)) {
								$user_rank = "mod";
							}
						}

						$room[$room_n]['users'][$arr['uuid']] = [];
						$room[$room_n]['users'][$arr['uuid']]['connection'] = $conn;
						
						$room[$room_n]['users'][$arr['uuid']]['rank'] = $user_rank;
					}
					$arr['room'] = $user[$data[2]]['room'];
					$arr['rank'] = $user_rank;
					
					logController("core", "Connection {$conn->resourceId} requested UUID {$arr['uuid']} in room {$arr['room']}");
					$conn->send(formatResponse($arr,"user_data"));

					if(isset($is_persistent)) {
						if($is_persistent) {
							$arr_motd = [];
							$arr_motd['msg'] = $database['rooms']->querySingle('SELECT MOTD FROM rooms WHERE NAME="' . $room_n . '"');
							$arr_motd['name'] = "MOTD";
							$arr_motd['is_sys'] = 1;

							$conn->send(formatResponse($arr_motd, "chatMessage"));
						}
					}

					foreach($room[$room_n]['users'] as $uuid => $user_data) {
						$conn_c = $user_data['connection'];

						if($conn_c !== $conn) {
							$arr_t = [];
							$arr_t['name'] = $user[$arr['uuid']]['name'];
							$arr_t['bg_color'] = $user[$arr['uuid']]['color'][0];
							$arr_t['fg_color'] = $user[$arr['uuid']]['color'][1];
							$arr_t['uuid'] = $arr['uuid'];
							$arr_t['rank'] = $arr['rank'];
							$conn_c->send(formatResponse($arr_t, "userList_data"));
							$conn_c->send(formatResponse($arr_t, "userJoined"));
						}
					}
					unset($user[$data[2]]);

					break;

				case "getRooms":
					$arr = [];
					$rooms = [];
					foreach($room as $room_n => $data) {
						$arr = [];
						$arr['name'] = $room_n;
						$arr['users'] = count($data['users']);
						$arr['nsfw'] = $data['NSFW'];
						$rooms[] = $arr;
					}
					$conn->send(formatResponse($rooms, "roomsData"));
					break;
			}
			break;

		case "room":
			if(!isset($data[1])) {
				clientError($conn, "You have not defined a room.");
				return;
			}
			$data[1] = strtolower($data[1]);

			if(!isset($data[2])) {
				clientError($conn, "User UUID is not defined.");
				return;
			}
			if(!isset($data[3])) {
				clientError($conn, "Verification is not defined.");
				return;
			}
			if(!isset($data[4])) {
				clientError($conn, "No subcommand is defined.");
				return;
			}

			if(!array_key_exists($data[1], $room)) {
				clientError($conn, "Room {$data[1]} does not exist.");
				return;
			}
			if(!array_key_exists($data[2], $user)) {
				clientError($conn, "This user does not exist.");
				return;
			}
			if($user[$data[2]]['verification'] != $data[3]) {
				clientError($conn, "Your user verification key did not match with the server's.");
				return;
			}

			switch($data[4]) {
				case "sendMsg":
					if(!isset($user[$data[2]]['lastAction'])) {
						$user[$data[2]]['lastAction'] = 0;
					}

					if(!isset($data[5])) {
						clientError($conn, "No message is defined.");
						return;
					}
					$tmp = str_replace(array(" ", "&lt;br&gt;", "&lt;br/&gt;"), "", $data[5]);
					if($tmp == "") {
						clientError($conn, "Blank messages are prohibited.");
						return;
					}

					// limiting messages to 2KB
					$data[5] = substr($data[5], 0, 2048);

					$avoid_msg = 0;
					if(canUseCommand($data[1], $data[2], "mod")) {
						$avoid_msg = 1;
					}

					if(microtime(true) - $user[$data[2]]['lastAction'] < 0.75 && !$avoid_msg) {
						clientError($conn, "You are sending messages too fast.");
						return;
					}
					$user[$data[2]]['lastAction'] = microtime(true);

					if(substr($data[5], 0, 1) == "/") {
						if(handleCommandData($data, $conn) != "invalid") {
							return;
						}
					}

					global $parsedown;

					$arr = [];
					$arr['is_sys'] = 0;
					$arr['msg'] = $data[5];

					$word_count = substr_count($arr['msg'], " ") + 1;
					$embed = checkEmbed((($word_count > 1) ? strstr($arr['msg'], ' ', true) : $arr['msg']), str_replace("#", "", $user[$data[2]]['color'][0]));
					if($embed != "") {
						//echo "Embed wasn't blank.";
						if($word_count > 1) {
							$arr['msg'] = substr($arr['msg'], strpos($arr['msg'], " "));
						} else {
							$arr['msg'] = "";
						}
					}
					$arr['msg'] = $embed . $parsedown->line(htmlspecialchars_decode($arr['msg']));

					$arr['name'] = $user[$data[2]]['name'];
					$arr['color'] = $user[$data[2]]['color'][0];
					$arr['uuid'] = $data[2];
					$arr['msg_uuid'] = uniqid();
					foreach($room[$data[1]]['users'] as $uuid => $user_data) {
						$conn_c = $user_data['connection'];
						$conn_c->send(formatResponse($arr,"chatMessage"));
					}
					break;

				case "getUsers":
					foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
						$conn_c = $user_data['connection'];
						$arr = [];
						$arr['name'] = $user[$uuid]['name'];
						$arr['bg_color'] = $user[$uuid]['color'][0];
						$arr['fg_color'] = $user[$uuid]['color'][1];
						$arr['uuid'] = $uuid;
						$arr['rank'] = $user_data['rank'];
						$conn->send(formatResponse($arr, "userList_data"));
					}
					break;

				case "leave":
					$name = $user[$data[2]]['name'];
					$color = $user[$data[2]]['color'][0];
					unset($room[$data[1]]['users'][$data[2]]);
					unset($user[$data[2]]);

					if(isset($data[5])) {
						$reason = $data[5];
					} else {
						$reason = "quitting";
					}

					foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
						$conn_c = $user_data['connection'];
						$arr = [];
						$arr['color'] = $color;
						$arr['name'] = $name;
						$arr['reason'] = $reason;
						$arr['uuid'] = $data[2];
						$conn_c->send(formatResponse($arr, "userLeave"));
					}

					$is_persistent = 0;
					$query = 'SELECT * FROM rooms WHERE NAME="' . $data[1] . '"';
					$results = $database['rooms']->query($query);
					while($row = $results->fetchArray()) {
						$is_persistent = 1;
					}

					if(count($room[$data[1]]['users']) < 1 && !$is_persistent) {
						unset($room[$data[1]]);
						logController("room/leave", "Unset room {$data[1]}");
					}
					break;
			}
			break;
	}
}

function generateKey($min, $max) {
	$chars = implode(",", range("a", "z")) . implode(",", range("A", "Z")) . implode(",", range(0, 9));
	$chars = explode(",", $chars);

	$amount = mt_rand($min, $max);
	$str = "";
	for($i = 0; $i < $amount; $i++) {
		$str .= $chars[array_rand($chars)];
	}

	return $str;
}

function checkEmbed($str, $color) {
	$str = htmlspecialchars_decode($str);
	//echo $str;
	if(filter_var($str, FILTER_VALIDATE_URL) === false) {
		return "";
	}

	$fragments = parse_url($str);
	$hostname = str_replace("www.", "", $fragments['host']);

	$valid_youtube = array(
		"youtube.com",
		"youtu.be"
	);
	$valid_soundcloud = array(
		"soundcloud.com"
	);
	$valid_pastebin = array(
		"pastebin.com"
	);
	$valid_vine = array(
		"vine.co"
	);
	$valid_imgur_gifv = array(
		"i.imgur.com",
		"imgur.com"
	);

	// i'd have twitter here if their API wasn't such a piece of shit
	// i'm not authenticating just to get embedded fucking tweets
	// the application would hit the rate limit within minutes anyways

	if(in_array($hostname, $valid_youtube)) {
		if($fragments['query'] == "") {
			$id = substr(str_replace("/", "", $fragments['path']), 0, 11);
		} else {
			$id = substr(str_replace("v=", "", $fragments['query']), 0, 11);
		}
		return '<div class="youtube-wrapper"><iframe width="100%" height="100%" src="https://www.youtube.com/embed/' . htmlspecialchars($id) . '" frameborder="0" allowfullscreen></iframe></div>';
	}
	if(in_array($hostname, $valid_soundcloud)) {
		return '<iframe width="100%" height="166" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=soundcloud.com' . htmlspecialchars($fragments['path']) . '&amp;color=' . $color . '&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false"></iframe>';
	}
	if(in_array($hostname, $valid_pastebin)) {
		$id = htmlspecialchars(str_replace("/", "", $fragments['path']));
		return '<iframe src="http://pastebin.com/embed_iframe.php?i=' . $id . '" style="border: none; width: 100%; height: 200px;"></iframe>';
	}
	if(in_array($hostname, $valid_vine)) {
		// this isn't needed, i just want the stupid iframe
		$json = json_decode(file_get_contents('https://vine.co/oembed.json?url=' . urlencode($str)), true);
		return $json['html'];
	}

	$audio_formats = array(
		"mp3", "ogg", "m4a", "wav",
		"flac", "opus", "aac", "wma"
	);
	$video_formats = array(
		"mp4", "webm", "avi", "mkv",
		"ogv", "wmv"
	);
	$image_formats = array(
		"jpg", "jpeg", "png", "bmp",
		"gif", "tiff", "svg"
	);

	$ext = pathinfo(htmlspecialchars($fragments['path']), PATHINFO_EXTENSION);
	if(in_array($ext, $audio_formats)) {
		return '<audio src="' . htmlspecialchars($str) . '" preload="metadata" controls></audio>';
	}
	if(in_array($ext, $video_formats)) {
		return '<video src="' . htmlspecialchars($str) . '" preload="metadata" controls></video>';
	}
	if(in_array($ext, $image_formats)) {
		return '<a href="' . htmlspecialchars($str) . '" target="_blank"><img src="' . htmlspecialchars($str) . '"/></a>';
	}

	if($ext == "gifv" && in_array($hostname, $valid_imgur_gifv)) {
		$id = htmlspecialchars(str_replace("." . $ext, "", $fragments['path']));
		$str = '<video autoplay loop poster="http://i.imgur.com/' . $id . 'l.jpg" id="backgroundgif">';
		$str .= '<source src="http://i.imgur.com/' . $id . '.webm" type="video/webm">';
		$str .= '<source src="http://i.imgur.com/' . $id . '.mp4" type="video/mp4">';
		$str .= '</video>';
		return $str;
	}
}

function canUseCommand($room_n, $uuid, $rank) {
	global $room;

	$vals = array(
		"owner" => 2,
		"mod" => 1,
		"user" => 0
	);

	if(!isset($room[$room_n]['users'][$uuid]['rank'])) {
		echo "not set\n";
		return 0;
	}
	if($room[$room_n]['users'][$uuid]['rank'] == "") {
		echo "blank\n";
		return 0;
	}

	$user_rank = $room[$room_n]['users'][$uuid]['rank'];
	return ($vals[$user_rank] >= $vals[$rank]) ? 1 : 0;
}

function handleCommandData($data, $conn) {
	$cmd = (substr_count($data[5], " ") >= 1) ? str_replace("/", "", strstr($data[5], ' ', true)) : str_replace("/", "", $data[5]);
	$arr = [];
	$arr['command'] = $cmd;
	$msg_fixed = str_replace("/" . $cmd, "", $data[5]);
	if(substr($msg_fixed, 0, 1) == " ") {
		$msg_fixed = substr($msg_fixed, 1);
	}

	global $room;
	global $user;

	switch($cmd) {
		case "help":
			$commands = array(
				"/help" => "This message",
				"/me *[text]*" => "Do an action",
				"/color *[number]*" => "Change your color",
				"/listcolors" => "List available colors",
				"/kick *[user]*" => "Kick a problematic user",
				"/ban *[time] [user]*" => "Ban a problematic user (example: '/ban 30m user' bans user for 30m)",
				"/unban *[user]*" => "Unban a user",
				"/listbans" => "List banned users",
				"/register *[password] [confirm]*" => "Register your username",
				"/persist" => "Make a channel persistent",
				"/mod" => "Make a user a moderator",
				"/demod" => "Demote a moderator back to a standard user",
				"/roll *[min=1] [max=6]*" => "Get a random number",
				"/nsfw" => "Mark/unmark the room as NSFW.",
				"/motd" => "Change the **Message Of The Day** seen when users join the room",
				"/clear" => "Clears messages from the chat area",
				"/trash *[user]*" => "Clears a user's messages from the room",
				"/pm *[user:msg]*" => "Send a private message to a user",
				"/ignore *[user]*" => "Ignore PMs from a user",
				"/unignore *[user]*" => "Receive PMs from an ignored user again"
			);
			$str = "";
			foreach ($commands as $cmd => $desc) {
				$str .= "<br>**$cmd** => *$desc*";
			}

			global $parsedown;
			$arr['msg'] = $parsedown->line(htmlspecialchars_decode($str));
			$arr['is_sys'] = 1;
			$arr['cmd'] = "help";

			$conn->send(formatResponse($arr,"chatCommand"));

			break;

		case "me":
			global $parsedown;

			$arr['is_sys'] = 0;

			$arr['msg'] = $parsedown->line(htmlspecialchars_decode($msg_fixed));

			$arr['name'] = $user[$data[2]]['name'];
			$arr['color'] = $user[$data[2]]['color'][0];
			$arr['uuid'] = $data[2];
			foreach($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr,"chatCommand"));
			}
			break;

		case "listcolors":
			global $root;
			include "$root/includes/colors.php";

			$arr['msg'] = "<br/>";
			$i = 0;
			foreach($colors as $color) {
				$i++;
				$tmp = explode(",", $color);
				$arr['msg'] .= '<div style="color:' . $tmp[0] . '; width: 96px; display: inline-block;">' . $i . '. <strong>' . $tmp[0] . '</strong></div>';
			}

			$arr['is_sys'] = 1;
			$arr['cmd'] = "listcolors";

			$conn->send(formatResponse($arr,"chatCommand"));
			break;

		case "kick":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			$arr['is_sys'] = 1;
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				if($user[$uuid]['name'] == $msg_fixed && $user[$uuid]['room'] == $data[1]) {
					$victim = $user[$uuid];
					$victim_uuid = $uuid; // wtf
					break 1;
				}
			}

			if(!isset($victim)) {
				clientError($conn, "This user does not exist.");
				return;
			}

			$arr['msg'] = "You have been kicked from the server.";
			$victim['conn']->send(formatResponse($arr, "chatMessage"));
			$victim['conn']->close();

			logController("command/kick", "$msg_fixed has been kicked from {$data[1]} by " . $user[$data[2]]['name']);
			$arr['msg'] = "$msg_fixed has been kicked from the room.";
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$arr['uuid'] = $victim_uuid;
				$arr['silent'] = 1;
				$conn_c->send(formatResponse($arr, "userLeave"));
				$conn_c->send(formatResponse($arr, "chatMessage"));
			}
			break;

		case "color":
			global $root;
			include "$root/includes/colors.php";

			if($msg_fixed < 1) {
				$msg_fixed = 1;
			}
			if($msg_fixed > count($colors)) {
				$msg_fixed = count($colors);
			}

			$chosen = ($msg_fixed - 1);

			$user[$data[2]]['color'] = explode(",", $colors[$chosen]);

			$arr = [];
			$arr['bg_color'] = $user[$data[2]]['color'][0];
			$arr['fg_color'] = $user[$data[2]]['color'][1];
			$arr['uuid'] = $data[2];

			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr, "updateColor"));
			}
			break;

		case "register":
			if(!isset($msg_fixed)) {
				return;
			}
			if($msg_fixed == "") {
				return;
			}

			if(substr(strtolower($user[$data[2]]['name']), 0, 5) == "guest") {
				clientError($conn, "Guest names are reserved.");
				return;
			}

			global $database;

			$user_exists = 0;
			$results = $database['users']->query('SELECT * FROM users WHERE USERNAME="' . $user[$data[2]]['name'] . '"');
			while($row = $results->fetchArray()) {
				$user_exists = 1;
			}

			if($user_exists) {
				clientError($conn, "You have already registered!");
				return;
			}

			$query = 'INSERT INTO users (USERNAME, HASH, REGISTERED, LAST_ACTIVE) VALUES (
				"' . $user[$data[2]]['name'] . '",
				"' . $msg_fixed . '",
				' . time() . ',
				' . time() . '
			)';
			$database['users']->exec($query);

			$arr['is_sys'] = 1;
			$arr['msg'] = "Your account has been successfully registered.";

			logController("command/register", "User " . $user[$data[2]]['name'] . " is now registered.");
			$conn->send(formatResponse($arr, "chatMessage"));
			break;

		case "persist":
			global $database;

			$user_exists = 0;
			$results = $database['users']->query('SELECT * FROM users WHERE USERNAME="' . $user[$data[2]]['name'] . '"');
			while($row = $results->fetchArray()) {
				$user_exists = 1;
			}
			if(!$user_exists) {
				clientError($conn, "You must be a registered user to make rooms persistent.");
				return;
			}

			if(!canUseCommand($data[1], $data[2], "owner")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			$room_exists = 0;
			$results = $database['rooms']->query('SELECT * FROM rooms WHERE NAME="' . $data[1] . '"');
			while($row = $results->fetchArray()) {
				$room_exists = 1;
			}
			if($room_exists) {
				clientError($conn, "This room is already persistent.");
				return;
			}

			$query = 'INSERT INTO rooms (NAME, CREATOR, REGISTERED, NSFW) VALUES (
				"' . strtolower($data[1]) . '",
				"' . $user[$data[2]]['name'] . '",
				' . time() . ',
				' . $room[$data[1]]['NSFW'] . '
			)';
			$database['rooms']->exec($query);

			$arr['is_sys'] = 1;
			$arr['msg'] = "Your room is now persistent.";

			logController("command/persist", "User " . $user[$data[2]]['name'] . " has made room {$data[1]} persistent.");
			$conn->send(formatResponse($arr, "chatMessage"));
			break;

		case "mod":
			if(!canUseCommand($data[1], $data[2], "owner")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			global $database;

			if(!isset($msg_fixed)) {
				return;
			}
			if($msg_fixed == "") {
				return;
			}

			$found_player = 0;
			foreach ($room[$data[1]]['users'] as $uuid => $player_data) {
				if($user[$uuid]['name'] == $msg_fixed && $user[$uuid]['room'] == $data[1]) {
					$found_player = 1;
					$found_uuid = $uuid;
					break;
				}
			}

			if(!$found_player) {
				clientError($conn, "This user does not exist.");
				return;
			}
			if($room[$data[1]]['users'][$found_uuid]['rank'] == "owner") {
				clientError($conn, "You cannot mod the room owner.");
				return;
			}

			$results = $database['rooms']->query('SELECT MODS FROM rooms WHERE NAME="' . $data[1] . '"');
			$tmp = $results->fetchArray();
			$mods = explode(";", $tmp['MODS']);

			if($room[$data[1]]['users'][$found_uuid]['rank'] != "user") {
				clientError($conn, "This user is already a higher rank.");
				return;
			}

			$mods[] = $msg_fixed;
			$list = implode(";", $mods);

			$user_is_registered = 0;
			$results = $database['users']->query('SELECT USERNAME FROM users WHERE USERNAME="' . $msg_fixed . '"');
			while($row = $results->fetchArray()) {
				$user_is_registered = 1;
			}

			if($user_is_registered) {
				$database['rooms']->exec('UPDATE rooms SET MODS="' . $list . '" WHERE NAME="' . $data[1] . '"');
				$arr['msg'] = "$msg_fixed is now a moderator.";
			} else {
				$arr['msg'] = "$msg_fixed is now a moderator, but since they are not registered it is only temporary.";
			}

			$room[$data[1]]['users'][$found_uuid]['rank'] = "mod";

			logController("command/mod", "User " . $user[$data[2]]['name'] . " has made user " . $msg_fixed . " a moderator.");
			$arr['is_sys'] = 1;
			$arr['uuid'] = $found_uuid;
			$arr['command'] = "mod";
			foreach ($room[$data[1]]['users'] as $uuid => $player_data) {
				$player_data['connection']->send(formatResponse($arr, "chatCommand"));
			}
			break;

		case "demod":
			if(!canUseCommand($data[1], $data[2], "owner")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			global $database;

			if(!isset($msg_fixed)) {
				return;
			}
			if($msg_fixed == "") {
				return;
			}

			$found_player = 0;
			foreach ($room[$data[1]]['users'] as $uuid => $player_data) {
				if($user[$uuid]['name'] == $msg_fixed && $user[$uuid]['room'] == $data[1]) {
					$found_player = 1;
					$found_uuid = $uuid;
					break;
				}
			}

			if(!$found_player) {
				clientError($conn, "This user does not exist.");
				return;
			}
			if($room[$data[1]]['users'][$found_uuid]['rank'] == "owner") {
				clientError($conn, "You cannot demod the room owner.");
				return;
			}

			$results = $database['rooms']->query('SELECT MODS FROM rooms WHERE NAME="' . $data[1] . '"');
			$tmp = $results->fetchArray();
			$mods = explode(";", $tmp['MODS']);

			if($room[$data[1]]['users'][$found_uuid]['rank'] != "mod") {
				clientError($conn, "This user is not a moderator.");
				return;
			}

			unset($mods[array_search($msg_fixed, $mods)]);
			$list = implode(";", $mods);

			$user_is_registered = 0;
			$results = $database['users']->query('SELECT USERNAME FROM users WHERE USERNAME="' . $msg_fixed . '"');
			while($row = $results->fetchArray()) {
				$user_is_registered = 1;
			}

			if($user_is_registered) {
				$database['rooms']->exec('UPDATE rooms SET MODS="' . $list . '" WHERE NAME="' . $data[1] . '"');
			}

			$room[$data[1]]['users'][$found_uuid]['rank'] = "user";

			logController("command/demod", "User " . $user[$data[2]]['name'] . " has demodded " . $msg_fixed);
			$arr['is_sys'] = 1;
			$arr['uuid'] = $found_uuid;
			$arr['command'] = "demod";
			$arr['msg'] = "$msg_fixed is no longer a moderator.";
			foreach ($room[$data[1]]['users'] as $uuid => $player_data) {
				$player_data['connection']->send(formatResponse($arr, "chatCommand"));
			}
			break;

		case "roll":
			$vals = explode(" ", $msg_fixed);
			if($vals[0] == "") {
				$min = 1;
				$max = 6;
			}
			if($vals[0] != "" && !isset($vals[1])) {
				$min = 1;
				$max = ($vals[0] < 100000000) ? $vals[0] : 100000000;
			}
			if(isset($vals[1])) {
				$min = $vals[0];
				$max = $vals[1];
			}

			$arr['is_sys'] = 0;

			$rand = mt_rand($min, $max);
			$arr['msg'] = "rolled a $rand";

			$arr['name'] = $user[$data[2]]['name'];
			$arr['color'] = $user[$data[2]]['color'][0];
			// /roll is essentialy /me with a vairable
			// if i need to give this its own command, then i'll change it
			$arr['command'] = "me";
			$arr['uuid'] = $data[2];
			foreach($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr,"chatCommand"));
			}
			break;

		case "motd":
			if(!canUseCommand($data[1], $data[2], "owner")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			global $database;

			$room_exists = 0;
			$results = $database['rooms']->query('SELECT * FROM rooms WHERE NAME="' . $data[1] . '"');
			while($row = $results->fetchArray()) {
				$room_exists = 1;
			}
			if(!$room_exists) {
				clientError($conn, "<em>Message Of The Day</em> functionality is only available on persistent rooms.");
				return;
			}

			if($msg_fixed == "") {
				clientError($conn, "MOTD text is blank.");
				return;
			}

			global $parsedown;

			$line = $parsedown->line($msg_fixed);

			$database['rooms']->exec('UPDATE rooms SET MOTD="' . $line . '" WHERE NAME="' . $data[1] . '"');

			$arr['msg'] = "<strong>MOTD was changed to:</strong><br/>$line";
			$arr['command'] = "motd";
			foreach($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr,"chatCommand"));
			}
			break;

		case "nsfw":
			if(!canUseCommand($data[1], $data[2], "owner")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			global $database;

			$room[$data[1]]['NSFW'] = $room[$data[1]]['NSFW'] ? 0 : 1;

			$room_exists = 0;
			$results = $database['rooms']->query('SELECT * FROM rooms WHERE NAME="' . $data[1] . '"');
			while($row = $results->fetchArray()) {
				$room_exists = 1;
			}
			if($room_exists) {
				$database['rooms']->exec('UPDATE rooms SET NSFW=' . $room[$data[1]]['NSFW'] . ' WHERE NAME="' . $data[1] . '"');
			}

			$arr['is_sys'] = 1;
			$arr['msg'] = "The room is " . ($room[$data[1]]['NSFW'] ? "now marked as NSFW. Please be warned of potentially unsafe content." : "no longer marked NSFW.");
			foreach($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr,"chatMessage"));
			}
			break;

		case "ban":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			# /ban 60m username
			# smhdwyp
			if($msg_fixed == "") {
				clientError($conn, "You have not defined a time or user.");
				return;
			}
			if(substr_count($msg_fixed, " ") < 1) {
				clientError($conn, "You have not defined a user.");
				return;
			}

			$time = strtolower(str_replace(" ", "", substr($msg_fixed, 0, stripos($msg_fixed, " "))));

			$units = range("a", "z");
			
			$value = str_replace($units, "", $time);
			if($value == "") {
				clientError($conn, "Invalid ban time value");
				return;
			}
			
			$unit = str_replace($value, "", $time);
			$unit = ($unit == "") ? "m" : $unit;

			$offset = 0;
			switch($unit) {
				case "s":
					$offset = $value;
					break;

				case "m":
					$offset = $value*60;
					break;

				case "h":
					$offset = $value*60*60;
					break;

				case "d":
					$offset = $value*60*60*24;
					break;

				case "w":
					$offset = $value*60*60*24*7;
					break;

				case "y": // why
					$offset = $value*60*60*24*365;
					break;
			}

			if($offset <= 0) {
				clientError($conn, "Ban time came out to be 0 seconds or less, make sure your units are valid. (s, m, h, d, w, y)");
				return;
			}
			$ends = time() + $offset;

			$username = substr($msg_fixed, stripos($msg_fixed, " ")+1);

			$found_player = 0;
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				if($user[$uuid]['name'] == $username && $user[$uuid]['room'] == $data[1]) {
					$found_player = 1;
					$found_uuid = $uuid;
					break;
				}
			}
			if(!$found_player) {
				clientError($conn, "This user does not exist. (tested $username)");
				return;
			}

			if($room[$data[1]]['users'][$found_uuid]['rank'] == "owner") {
				clientError($conn, "You cannot ban the room owner.");
				return;
			}

			$victim = $user[$found_uuid];
			$IP = $victim['conn']->remoteAddress;
			if($IP == "") {
				clientError($conn, "There's no IP associated with this user, this shouldn't happen.");
				return;
			}

			// clientError($conn, "TEST: Message = $msg_fixed<br/>Time wanted = $value$unit<br/>Seconds = $offset<br/>End = $ends<br/>Username = $username<br/>IP = $IP");
			// return;

			if($room[$data[1]]['persistent']) {
				global $database;

				$bans_str = $database['rooms']->querySingle('SELECT BANS FROM rooms WHERE NAME="' . $data[1] . '"');
				if($bans_str != "") {
					$bans = explode(";", $bans_str);
				} else {
					$bans = [];
				}

				$bans[] = $victim['conn']->remoteAddress . "^" . $ends . "^" . $username;

				$new_bans = implode(";", $bans);
				$database['rooms']->exec('UPDATE rooms SET BANS="' . $new_bans . '" WHERE NAME="' . $data[1] . '"');
			}

			$room[$data[1]]['bans'][$IP] = [];
			$room[$data[1]]['bans'][$IP]['ends'] = $ends;
			$room[$data[1]]['bans'][$IP]['username'] = $username;

			$arr['msg'] = "You have been banned from the room.";
			$arr['ends'] = $ends;
			$arr['command'] = "ban";
			$victim['conn']->send(formatResponse($arr, "chatCommand"));
			$victim['conn']->close();

			logController("command/kick", "$username has been banned from {$data[1]} by " . $user[$data[2]]['name'] . " until $ends");
			unset($arr['command']);

			$arr['uuid'] = $found_uuid;
			$arr['name'] = $username;
			$arr['msg'] = "$username has been banned from the room.";
			$arr['silent'] = 1;

			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr, "banMessage"));
				$conn_c->send(formatResponse($arr, "userLeave"));
			}

			break;

		case "unban":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			if($msg_fixed == "") {
				clientError($conn, "You have not defined a user.");
				return;
			}

			$found_user = 0;
			foreach ($room[$data[1]]['bans'] as $IPt => $ban_data) {
				if($ban_data['username'] == $msg_fixed) {
					$IP = $IPt;
					$found_user = 1;
				}
			}
			if(!$found_user) {
				clientError($conn, "This user is not banned.");
				return;
			}

			// clientError($conn, "TEST: Message = $msg_fixed<br/>Time wanted = $value$unit<br/>Seconds = $offset<br/>End = $ends<br/>Username = $msg_fixed<br/>IP = $IP");
			// return;

			if($room[$data[1]]['persistent']) {
				global $database;

				$bans_str = $database['rooms']->querySingle('SELECT BANS FROM rooms WHERE NAME="' . $data[1] . '"');
				$bans = explode(";", $bans_str);

				//PHP doesn't like this, dunno why
				//$IP_list = array_map("stripBanEnds", $bans);

				$IP_list = [];
				for($i = 0; $i < count($bans); $i++) {
					$IP_list[] = stripBanEnds($bans[$i]);
				}
				$key = array_search($IP, $IP_list);

				unset($bans[$key]);
				$bans = array_values($bans);

				$new_bans = implode(";", $bans);
				$database['rooms']->exec('UPDATE rooms SET BANS="' . $new_bans . '" WHERE NAME="' . $data[1] . '"');
			}

			unset($room[$data[1]]['bans'][$IP]);

			logController("command/kick", "$msg_fixed has been unbanned from {$data[1]} by " . $user[$data[2]]['name']);

			$arr['msg'] = "$msg_fixed has been unbanned from the room.";
			$arr['is_sys'] = 1;

			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr, "chatMessage"));
			}

			break;

		case "listbans":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			$arr = [];
			$arr['is_sys'] = 1;

			if(count($room[$data[1]]['bans']) < 1) {
				$arr['msg'] = "No bans to list. :D!";
				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			$arr['username'] = [];
			$arr['IP'] = [];
			$arr['ends'] = [];
			foreach ($room[$data[1]]['bans'] as $IP => $ban_data) {
				$arr['username'][] = $ban_data['username'];
				$arr['IP'][] = $IP;
				$arr['ends'][] = $ban_data['ends'];
			}

			$arr['count'] = count($arr['username']);
			$arr['command'] = "listbans";

			$conn->send(formatResponse($arr, "chatCommand"));
			break;

		case "trash":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			if($msg_fixed == "") {
				clientError($conn, "You have not defined a user.");
				return;
			}

			$found_user = 0;
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				if($user[$uuid]['name'] == $msg_fixed && $user[$uuid]['room'] == $data[1]) {
					$found_user = 1;
					$found_uuid = $uuid;
					break;
				}
			}
			if(!$found_user) {
				clientError($conn, "This user does not exist.");
				return;
			}

			$arr['command'] = "trash";
			$arr['name'] = $msg_fixed;
			$arr['uuid'] = $found_uuid;

			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$conn_c = $user_data['connection'];
				$conn_c->send(formatResponse($arr, "chatCommand"));
			}
			break;

		case "pm":
			if(str_replace(" ", "", $msg_fixed) == "") {
				clientError($conn, "Nothing was defined.");
				return;
			}

			$wanted_user = substr($msg_fixed, 0, stripos($msg_fixed, ":"));
			$message = substr($msg_fixed, stripos($msg_fixed, ":")+1);

			if(str_replace(" ", "", $wanted_user) == "") {
				clientError($conn, "No user was defined.");
				return;
			}
			if(str_replace(" ", "", $message) == "") {
				clientError($conn, "Blank PMs are prohibited.");
				return;
			}

			$found_user = 0;
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				if($user[$uuid]['name'] == $wanted_user && $user[$uuid]['room'] == $data[1]) {
					$found_user = 1;
					$found_uuid = $uuid;
					break;
				}
			}
			if(!$found_user) {
				clientError($conn, "This user does not exist.");
				return;
			}

			$arr['sender'] = $user[$data[2]]['name'];
			$arr['recipient'] = $wanted_user;
			$arr['color'] = $user[$data[2]]['color'][0];
			$arr['msg'] = $message;
			$arr['pm'] = 1;

			global $parsedown;
			$arr['msg'] = $parsedown->line(htmlspecialchars_decode($arr['msg']));

			$conn->send(formatResponse($arr, "chatMessage"));
			if(!in_array($user[$data[2]]['name'], $user[$found_uuid]['ignored'])) {
				// better to not give any indication back at all
				// if you come at me wanting an API response for this, screw off
				$user[$found_uuid]['conn']->send(formatResponse($arr, "chatMessage"));
			}
			break;

		case "ignore":
			if(str_replace(" ", "", $msg_fixed) == "") {
				clientError($conn, "Nothing was defined.");
				return;
			}
			if(in_array($msg_fixed, $user[$data[2]]['ignored'])) {
				clientError($conn, "You already ignore this user.");
				return;
			}
			// i'm sorry, i need to
			if(count($user[$data[2]]['ignored']) >= 100) {
				clientError($conn, "You can only ignore a maximum of 100 users per session. Clients can manage higher amounts on their own if needed.");
			}

			$user[$data[2]]['ignored'][] = $msg_fixed;

			$arr['is_sys'] = 1;
			$arr['msg'] = "You are now ignoring PMs from $msg_fixed";
			$conn->send(formatResponse($arr, "chatMessage"));
			break;

		case "unignore":
			if(str_replace(" ", "", $msg_fixed) == "") {
				clientError($conn, "Nothing was defined.");
				return;
			}
			if(!in_array($msg_fixed, $user[$data[2]]['ignored'])) {
				clientError($conn, "You do not ignore this user.");
				return;
			}

			unset($user[$data[2]]['ignored'][array_search($msg_fixed, $user[$data[2]]['ignored'])]);

			$arr['is_sys'] = 1;
			$arr['msg'] = "You are no longer ignoring PMs from $msg_fixed";
			$conn->send(formatResponse($arr, "chatMessage"));
			break;

		case "remove":
			if(!canUseCommand($data[1], $data[2], "mod")) {
				$arr['is_sys'] = 1;
				$arr['msg'] = "You do not have sufficent privileges to use this command.";

				$conn->send(formatResponse($arr, "chatMessage"));
				return;
			}

			if(!isset($msg_fixed)) {
				clientError($conn, "No message UUID defined");
				return;
			}

			$arr['msg_uuid'] = $msg_fixed;
			$arr['command'] = "remove";
			foreach ($room[$data[1]]['users'] as $uuid => $user_data) {
				$user_data['connection']->send(formatResponse($arr, "chatCommand"));
			}
			break;

		default:
			return "invalid";
			break;
	}
	return 1;
}