<?php

use Workerman\Worker;
use PHPSocketIO\SocketIO;
require_once __DIR__ . '/vendor/autoload.php';

$io = new SocketIO(getenv('PORT'));
$io->origins(getenv('DOMAIN'));

$io->on('connection', function ($socket) use ($io) {
    $socket->on('online', function ($name) use ($socket) {
        if (!isset($socket->name)) {
            $socket->name = $name;
            $socket->broadcast->emit('online', [$socket->name.' đã tham gia', 'success']);
        }
    });
    $socket->on('chat', function ($msg) use ($socket) {
        if (is_array($msg) && array_keys($msg) == ['data', 'signature']) {
            [$data, $signature] = array_values($msg);
            $hash = hash_hmac('md2', json_encode($data), getenv('SECRET'));
            if ($signature == $hash) {
                $socket->broadcast->emit('chat', $data);
            }
        }
    });
    $socket->on('disconnect', function () use ($socket) {
        $socket->broadcast->emit('online', [$socket->name.' đã thoát', 'error']);
    });
});

Worker::runAll();
