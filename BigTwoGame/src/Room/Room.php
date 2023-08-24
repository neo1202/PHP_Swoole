<?php

namespace MyApp\Room;

use MyApp\Config\Config;
use MyApp\Game\Game;

class Room
{
    public $roomId;
    public $playerIds;
    public $game;
    public $server;
    public $haveGameStarted;

    public function __construct($myServer, $myRoomId)
    {
        $this->server = $myServer;
        $this->roomId = $myRoomId;
        $this->playerIds = []; // [$fd, true/false]
        $this->haveGameStarted = false;
    }

    public function tryStartGame()
    {
        if ($this->isFull() && $this->countPlayersReady() == 4) {
            $this->roomBroadCast(Config::$colorCode['GREEN'] . "\nThis room is full and the players are ready!!!\n\n" . Config::$colorCode['RESET']);
            echo "\n\nThis room is full and the players are ready!!!\n";
            sleep(0.5);
            $this->roomBroadCast("Start the game in 3\n");
            echo "Start the game in 3\n";
            sleep(1);
            $this->roomBroadCast("Start the game in 2\n");
            echo "Start the game in 2\n";
            sleep(1);
            $this->roomBroadCast("Start the game in 1\n");
            echo "Start the game in 1\n";
            sleep(1);
            $this->haveGameStarted = true;
            $this->startGame();
        }
    }

    public function addPlayerId($fd)
    {
        $this->playerIds[] = [$fd, false];
        echo "player number in this room_{$this->roomId}: " . count($this->playerIds) . "\n";
    }
    public function isFull()
    {
        return count($this->playerIds) === 4;
    }
    public function countPlayersReady() // 計算此房間多少人準備開始了
    {
        $cnt = 0;
        foreach ($this->playerIds as $onePlayer) {
            if ($onePlayer[1]) {
                $cnt += 1;
            }
        }
        return $cnt;
    }

    public function startGame()
    {
        echo "Player fds in this game:\n";
        print_r(array_column($this->playerIds, 0));
        $this->game = new Game(array_column($this->playerIds, 0), $this->server);
    }

    public function removePlayerByFd($fd) // 把此房間中某位player移除
    {
        $index = array_search($fd, array_column($this->playerIds, 0));
        $who = $index+1;
        echo "Player_{$who} leaves\n";
        if ($index !== false) {
            unset($this->playerIds[$index]);
            $this->playerIds = array_values($this->playerIds);
            echo "player number in this room_{$this->roomId} after player_{$who} leaves: " . count($this->playerIds) . "\n";
            $this->roomBroadCast(Config::$colorCode['RED'] . "Player_{$who} leaves. Player number in this room_{$this->roomId}:" . count($this->playerIds) . Config::$colorCode['RESET'] . "\n");
            // 如果當前還在遊戲，強制退出
            if ($this->haveGameStarted) {
                $this->roomBroadCast(Config::$colorCode['RED'] . "OHHHHH NO ~ ~ ~ Player_{$who} have left the game. Sorry\ntype `ready` for a new game:\n" . Config::$colorCode['RESET']);
                $this->resetGame();
            }
        }
    }

    public function setPlayerStatusById($fd, $status)
    {
        foreach ($this->playerIds as $index => &$onePlayer) {
            if ($onePlayer[0] === $fd) {
                $who = $index+1;
                if ($status == 'ready') {
                    echo "I(Player_{$who}) am ready\n";
                    $this->roomBroadCast("Player_{$who}(id_{$fd}) ready\n");
                    $onePlayer[1] = true;
                } else {
                    echo "I(Player_{$who}) am not ready\n";
                    $this->roomBroadCast("Player_{$who}(id_{$fd}) not ready\n");
                    $onePlayer[1] = false;
                }
                break;
            }
        }
        $this->roomBroadCast("{$this->countPlayersReady()} players are ready to start the Game.\n");
    }
    public function resetGame()
    {
        $this->haveGameStarted = false;
        foreach ($this->playerIds as &$onePlayer) {
            $onePlayer[1] = false;
        }
    }
    public function roomBroadCast($word)
    {
        foreach ($this->playerIds as $onePlayer) {
            $this->server->send($onePlayer[0], "Room Broadcast: {$word}");
        }
    }
}
