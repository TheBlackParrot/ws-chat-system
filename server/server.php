<?php
if(php_sapi_name() != "cli") {
	die("CLI-only");
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use chat_controller\ChatController;

	require dirname(__FILE__) . '/vendor/autoload.php';

	$server = IoServer::factory(
		new HttpServer(
			new WsServer(
				$cont = new ChatController()
			)
		),
		9092
	);

	print("Running...\r\n");

	$server->run();