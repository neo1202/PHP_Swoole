<?php

namespace MyApp\Game\Card;

class Card
{
    public $suit;  // 花色 spade, heart, diamond, club
    public $number;  // 數字

    public function __construct($suit, $number)
    {
        $this->suit = $suit;
        $this->number = $number;
    }

    public function getCardInfo()
    {
        return 'Card Info... `Suit: ' . $this->suit . ', Number: ' . $this->number . "`\n";
    }
}
