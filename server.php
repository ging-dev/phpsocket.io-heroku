<?php

use Workerman\Worker;
use PHPSocketIO\SocketIO;
require_once __DIR__ . '/vendor/autoload.php';

$io = new SocketIO(getenv('PORT'));
$io->origins(getenv('DOMAIN'));

$io->on('connection', function ($socket) use ($io) {
    $socket->on('demo', function ($data) use ($io) {
        $io->emit('demo', $data);
    });
});

Worker::runAll();
