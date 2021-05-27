<?php

use Workerman\Worker;
use PHPSocketIO\SocketIO;
use Workerman\Protocols\Http\Request;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

$io = new SocketIO(3000);
$io->origins(getenv('DOMAIN'));
$users = [];

$io->on('workerStart', function() use ($io) {
    $inner_http_worker = new Worker('http://0.0.0.0:3001');
    $inner_http_worker->onMessage = function(TcpConnection $http_connection, Request $request) use ($io) {
        if(!isset($request->get()['message'])) {
            return $http_connection->send('Fail, "message" not found');
        }
        $io->emit('online', [$request->get('message'), 'success']);
        $http_connection->send('OK');
    };
    $inner_http_worker->listen();
});

$io->on('connection', function ($socket) use ($io, $users) {
    $socket->on('online', function ($name) use ($socket, &$users) {
        if (!in_array($name, $users)) {
            $socket->name = $name;
            $users[] = $name;
            $socket->broadcast->emit('online', [$socket->name.' đã tham gia', 'success']);
            $socket->broadcast->emit('online', [implode(', ', $users), 'info']);
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

    $socket->on('disconnect', function () use ($socket, &$users) {
        if (isset($socket->name)) {
            if (($key = array_search($socket->name, $users)) !== false) {
                unset($users[$key]);
                if ($users) {
                    $socket->broadcast->emit('online', [implode(', ', $users), 'info']);
                }
            }
            $socket->broadcast->emit('online', [$socket->name.' đã thoát', 'error']);
        }
    });
});

Worker::runAll();
