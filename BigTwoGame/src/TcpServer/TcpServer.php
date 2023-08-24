<?php

//创建Server对象, 监听 127.0.0.1:9501 端口。

namespace MyApp\TcpServer;

use MyApp\Config\Config;
use MyApp\Game\Game;
use MyApp\Room\Room;
use MyApp\ServerController\ServerController;

class TcpServer
{
    public $roomCnt; //從第0+1間房開始, 第一間房對應在rooms[0]
    public $server;
    public $serverController;
    public $rooms;

    public function __construct()
    {
        echo "hell yeah, constructing server...\n";
        $this->roomCnt = 0;
        $this->rooms = [];
        $this->server = new \Swoole\Server('127.0.0.1', 9501);
        $this->server->set([
            'worker_num' => 1,
            'max_request' => 200000,
        ]);
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->serverController = new ServerController();
        $this->server->start();
    }

    public function onStart($server)
    {
        echo 'Ready on 127.0.0.1.9501' . PHP_EOL;
        echo "Testing ServerController: 5+1=?" . $this->serverController->addOne(5) . "!!!\n"; // ServerController
    }

    public function onConnect($server, $fd)
    {
        echo "客戶端id: {$fd} 連接.\n";
        $this->handleConnection($fd);
    }

    public function onReceive($server, $fd, $reactor_id, $data) // $server, $fd, $data
    {
        $data = strtolower(trim($data));
        $currentRoomId = $this->serverController->getRoomId($fd, $this->rooms);
        if ($currentRoomId == null) {
            echo "system break";
            print_r($this->rooms);
        }
        echo "The message is from Room_{$currentRoomId}.\n";
        $currentRoom = $this->rooms[$currentRoomId-1];
        if (($data == 'ready' || $data == 'unready') && $currentRoom->haveGameStarted == false) {
            $currentRoom->setPlayerStatusById($fd, $data);
            echo "someone {$data}, how many player in Room_{$currentRoomId} is ready? {$currentRoom->countPlayersReady()} players.\n";
            $currentRoom->tryStartGame();
        } elseif ($currentRoom->haveGameStarted == true) {
            [$status, $response] = $currentRoom->game->processInput($fd, $data);
            echo "status: {$status}, response: {$response}\n";
            if ($status == 2) { //遊戲結束，廣播response給所有人
                $currentRoom->game->broadCast(Config::$colorCode['YELLOW'] . $response . Config::$colorCode['RESET']);
                $currentRoom->resetGame();
                $currentRoom->game->broadCast(Config::$colorCode['YELLOW'] . "\n\n你可以再度準備for another Game, type `ready` to continue:\n" . Config::$colorCode['RESET']);
            } elseif ($status == 1) { //pass或是正確出牌，但牌局還沒結束
                $this->server->send($fd, Config::$colorCode['GREEN'] . $response . Config::$colorCode['RESET']);
            } else { //status=2代表不正確出牌
                $this->server->send($fd, Config::$colorCode['RED'] . $response . Config::$colorCode['RESET']);
            }
        } else {
            $this->server->send($fd, Config::$colorCode['RED'] . "Game haven't start yet... waiting for other players\n" . Config::$colorCode['RESET']);
        }
    }
    public function onClose($server, $fd)
    {
        echo "客户端id: {$fd}关闭.\n";
        $currentRoomId = $this->serverController->getRoomId($fd, $this->rooms);
        $currentRoom = $this->rooms[$currentRoomId-1];
        $currentRoom->removePlayerByFd($fd);
    }

    public function handleConnection($fd)
    {
        $currentAvailableRoomId = $this->serverController->getAvailableRoom($this->rooms);
        if ($currentAvailableRoomId === null) {
            echo "There is no room, create a new one...\n";
            $this->roomCnt += 1;
            $currentAvailableRoomId = $this->roomCnt;
            $newRoom = new Room($this->server, $this->roomCnt);
            $this->rooms[] = $newRoom;
        }
        $currRoom = $this->rooms[$currentAvailableRoomId-1];
        $currRoom->addPlayerId($fd); #room中會檢查滿人了沒，滿了就開始
        $this->server->send($fd, Config::$colorCode['YELLOW'] . "\nType `ready` / `unready` for the BigTwo Game :)\n" . Config::$colorCode['RESET']);
    }
}
