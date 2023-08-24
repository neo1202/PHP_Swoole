<?php

echo "Hi start\n";

require_once __DIR__ . '/vendor/autoload.php';

use MyApp\Config\Config;
use MyApp\TcpServer\TcpServer;
use MyApp\Game\Game;
use MyApp\Game\Player\Player;
use MyApp\Game\Card\Card;
use MyApp\Game\Deck\Deck;

$myServer = new TcpServer();
