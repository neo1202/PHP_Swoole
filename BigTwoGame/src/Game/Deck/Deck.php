<?php

namespace MyApp\Game\Deck;

use MyApp\Config\Config;
use MyApp\Game\Card\Card;

class Deck
{
    public $cards;  // 牌組
    public $decksize; //幾張牌
    private $suitOrder; //大小順序
    private $server;

    public function __construct($cards, $myServer)
    {
        $this->server = $myServer;
        $this->cards = $cards;
        $this->decksize = count($this->cards);
        $this->suitOrder = Config::$config['suitOrder'];
        $this->sortTheCards();
    }

    public function getDeckSize()
    {
        return count($this->cards);
    }

    public function removeCardsByIndex(array $indexesToRemove)
    {
        $updatedCards = [];
        foreach ($this->cards as $index => $card) {
            if (!in_array($index, $indexesToRemove)) {
                $updatedCards[] = $card;
            }
        }
        $this->cards = $updatedCards;
    }

    public function containClubThree(array $indexes)
    {
        $contain = false;
        foreach ($this->cards as $index => $card) {
            if (in_array($index, $indexes)) {
                if ($card->suit == 'club' && $card->number == 3) {
                    $contain = true;
                }
            }
        }
        return $contain;
    }

    public function printAllCards($fd, $which_player = ' now')
    {
        $this->server->send($fd, "\nPlayer" . $which_player . "'s Deck: \n");
        foreach ($this->cards as $index => $card) {
            $this->server->send($fd, 'Idx: ' . $index . ',   Suit: ' . $card->suit . ',   Number: ' . $card->number . "\n");
        }
    }

    private function compareCards($card1, $card2) //1代表需要換位子, -1代表不用, 0代表兩者相等也不用換
    {// Sort by number first
        if ($card1->number < $card2->number) {
            return 1;
        } elseif ($card1->number > $card2->number) {
            return -1;
        } else {
            // If numbers are equal, sort by suit
            $suitIndex1 = array_search($card1->suit, $this->suitOrder);
            $suitIndex2 = array_search($card2->suit, $this->suitOrder);

            if ($suitIndex1 < $suitIndex2) {
                return -1;
            } elseif ($suitIndex1 > $suitIndex2) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    private function sortTheCards()
    {
        usort($this->cards, [$this, 'compareCards']);
    }

    public function calculateCombo(array $idxArr) // 使用給定Card index計算牌型
    {
        $combo = '無結果';
        $largest = null;
        $numberArr = [];
        $suitArr = [];
        echo "\n我這次嘗試要出的牌: \n";
        foreach ($this->cards as $index => $oneCard) {
            if (in_array($index, $idxArr)) { //在這次準備要出的牌裡面
                $numberArr[] = $oneCard->number;
                $suitArr[] = $oneCard->suit;
                echo $oneCard->getCardInfo();
            }
        }
        $suit_unique = count(array_unique($suitArr));
        $number_unique = count(array_unique($numberArr));
        if (count($idxArr) == 5) {
            //echo "\nTry 順子和鐵支和葫蘆";
            if ($number_unique == 5 && ($numberArr[0] - $numberArr[4] == 4) || ($numberArr[4]==1 && $numberArr[3]==10)) {
                $combo = '順子';
                $largest = new Card($suitArr[0], $numberArr[0]);
                if ($numberArr[4] == 2 || ($numberArr[4] == 1 && $numberArr[3]==10)) { //23456, 10jqka比2/1, 12345比5
                    $largest = new Card($suitArr[4], $numberArr[4]);
                }
            } elseif ($number_unique == 2) {
                if ((count(array_unique(array_slice($numberArr, 0, 4))) == 1) || (count(array_unique(array_slice($numberArr, 1, 5))) == 1)) {
                    $combo = '鐵支';
                    if ($numberArr[0] == $numberArr[1]) {
                        $largest = new Card($suitArr[0], $numberArr[0]); //如55552
                    } else {
                        $largest = new Card($suitArr[1], $numberArr[1]); //如21111
                    }
                } else {
                    $combo = '葫蘆';
                    if ($numberArr[1] == $numberArr[2]) {
                        $largest = new Card($suitArr[0], $numberArr[0]); //如55522
                    } else {
                        $largest = new Card($suitArr[2], $numberArr[2]); //如22111
                    }
                }
            }
        } elseif (count($idxArr) == 2 && $numberArr[0] == $numberArr[1]) {
            $combo = '對子';
            $largest = new Card($suitArr[0], $numberArr[0]);
        } elseif (count($idxArr) == 1) {
            $combo = '單張';
            $largest = new Card($suitArr[0], $numberArr[0]);
        } else {
            //沒有匹配的
        }
        return [$largest, $combo];
    }
}
