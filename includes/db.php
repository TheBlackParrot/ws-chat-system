<?php
if(php_sapi_name() != "cli") {
	die("CLI-only");
}

$database = [];
$database['setting'] = [];

$database['setting']['root_dir'] = "/srv/http/chat_files/database/";
$database['setting']['rooms_db_file'] = $database['setting']['root_dir'] . "rooms.db";
$database['setting']['users_db_file'] = $database['setting']['root_dir'] . "users.db";

if(!is_dir($database['setting']['root_dir'])) {
	if(!mkdir($database['setting']['root_dir'], 0700, true)) {
    	die('Failed to create root folder...');
	}
}

$database['rooms'] = new SQLite3($database['setting']['rooms_db_file']);
$database['users'] = new SQLite3($database['setting']['users_db_file']);

$query = "CREATE TABLE IF NOT EXISTS rooms (
	ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	NAME TEXT NOT NULL,
	MODS TEXT,
	CREATOR VARCHAR(32) NOT NULL,
	REGISTERED INT(24) NOT NULL,
	MOTD TEXT,
	HASH TEXT,
	VERIFY_HASH TEXT,
	NSFW INT(1) NOT NULL,
	BANS TEXT
)";
$database['rooms']->exec($query);

$query = "CREATE TABLE IF NOT EXISTS users (
	ID INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	USERNAME VARCHAR(32) NOT NULL,
	HASH TEXT NOT NULL,
	VERIFY_HASH TEXT,
	REGISTERED INT(24) NOT NULL,
	LAST_ACTIVE INT(24) NOT NULL
)";
$database['users']->exec($query);

$GLOBALS['database'] = $database;