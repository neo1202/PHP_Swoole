<?php

namespace MyApp\Game\Player;

class Player
{
    public $cardDeck;
    public $havePassed; //false代表沒有pass, 還能出牌. ture代表pass過了，要等全部人pass才能進入下一輪combo

    public function __construct($cardDeck)
    {
        $this->cardDeck = $cardDeck;
        $this->havePassed = false;
    }
}
