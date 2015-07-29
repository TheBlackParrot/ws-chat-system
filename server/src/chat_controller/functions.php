<?php
namespace chat_controller;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatController implements MessageComponentInterface {
    public function onOpen(ConnectionInterface $conn) {
    }

    public function onMessage(ConnectionInterface $from, $msg) {
    }

    public function onClose(ConnectionInterface $conn) {
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
    }
}