<?php

namespace MyApp\Game;

use MyApp\Config\Config;
use MyApp\Game\Card\Card;
use MyApp\Game\Deck\Deck;
use MyApp\Game\Player\Player;

class Game
{
    public $players;
    private $player_fds;
    public $who_dominate; //誰的牌權
    private $suitOrder;
    private $numberOrder;
    private $server;

    public $turn;
    public $passedSum; //總共多少人pass了
    public $feel_free; //這輪想出什麼就出什麼嗎
    public $previous_round_largest;
    public $previous_round_type;
    public $is_first_round;
    public $isGameEnd; //true代表結束了

    public function __construct(array $player_ids, $myServer)
    {
        $this->turn = 0;
        $this->passedSum = 0;
        $this->player_fds = $player_ids;
        $this->server = $myServer;
        foreach ($player_ids as $fd) {
            $this->server->send($fd, Config::$colorCode['GREEN'] . "You (id_{$fd}) joined the new Game!!!\n" . Config::$colorCode['RESET']);
        }
        $this->suitOrder = Config::$config['suitOrder'];
        $this->numberOrder = Config::$config['numberOrder'];
        $this->generateDeck();
    }

    private function generateDeck()
    {
        $cards = [];
        $suits = ['spade', 'heart', 'diamond', 'club'];

        foreach ($suits as $suit) {
            for ($number = 1; $number <= 13; $number++) {
                $cards[] = new Card($suit, $number);
            }
        }
        shuffle($cards);

        $desiredIndex = -1;
        foreach ($cards as $index => $card) {
            if ($card->number == 3 && $card->suit == 'club') {
                $desiredIndex = $index;
                break;
            }
        }
        echo '梅花三在第' . $desiredIndex . "張\n";

        $this->players = [];
        for ($i = 1; $i <= 4; $i++) {
            $start = ($i - 1) * 13;
            $personCards = array_slice($cards, $start, 13);
            $player_deck = new Deck($personCards, $this->server);
            $player = new Player($player_deck);
            $player->havePassed = false;
            $player->cardDeck->printAllCards($this->player_fds[$i-1], $i);
            $this->server->send($this->player_fds[$i-1], Config::$colorCode['BOLD'] . "\n卡牌就緒, 準備開始遊戲!\n" . Config::$colorCode['RESET']);
            $this->players[] = $player;
        }

        if ($desiredIndex >= 0 && $desiredIndex <= 12) {
            $start_player = 1;
        } elseif ($desiredIndex >= 13 && $desiredIndex <= 25) {
            $start_player = 2;
        } elseif ($desiredIndex >= 26 && $desiredIndex <= 38) {
            $start_player = 3;
        } elseif ($desiredIndex >= 39 && $desiredIndex <= 51) {
            $start_player = 4;
        } else {
            echo 'Club Three Number is outside the specified ranges';
        }
        $this->broadCast("Player " . $start_player . " start first !\n");
        echo "\nPlayer " . $start_player . " start first.\n";
        $this->who_dominate = $start_player;
        $this->gameStart($start_player);
    }

    public function gameStart($start_player) //傳入是誰, 1234
    {
        $this->turn = $start_player;
        $this->feel_free = true; //這輪想出什麼就出什麼嗎
        $this->previous_round_largest = [];
        $this->previous_round_type = '';
        $this->is_first_round = true;
        $this->isGameEnd = false;
        $this->server->send($this->player_fds[$start_player-1], Config::$colorCode['YELLOW'] . "\n\nYou start first!\nIn first round, You MUST use Club 3 !!!\nPlease enter numbers separated by whitespace !!!\n" . Config::$colorCode['RESET']);
        $this->server->send($this->player_fds[$start_player-1], Config::$colorCode['GREEN'] . "(feel free to choose your card)" . Config::$colorCode['RESET']);
        $this->server->send($this->player_fds[$start_player-1], ", Your Decision: ");
    }

    public function processInput($fd, $input)
    {
        # 檢查遊戲結束沒
        if ($this->isGameEnd) {
            echo "遊戲已經結束了 不能出牌";
            return [0, "遊戲已經結束了 不能出牌\n"];
        }
        # 檢查是不是換他出牌
        $from_player = array_search($fd, $this->player_fds);
        if ($from_player != ($this->turn-1)) {
            echo "\nIt's Player" . $this->turn . "'s turn ! and the message is from player_" . $from_player+1 . "\n";
            return [0, "It's not your turn!!! It's player_{$this->turn}'s turn\n"];
        }
        $currentPlayer = $this->players[$this->turn-1]; #指向players[]物件
        # 到此代表是他(此$fd)該出牌沒錯，檢查有沒有成功出牌
        echo "誰出牌: Player_{$this->turn}, 誰的牌權: Player_{$this->who_dominate}\n";
        if ($input === 'fraud') { // 直接開後門作弊勝利
            $this->isGameEnd = true;
            echo "\n\nfinish! the winner is Player_{$this->turn} !!!\n";
            $this->server->send($fd, Config::$colorCode['GREEN'] . "成功的一次出牌，你獲得了勝利 ~ ~ ~ \n" . Config::$colorCode['RESET']);
            return [2, "遊戲結束! the winner is 玩家_{$this->turn} !!!\n"];
        }
        if ($input === 'pass') {
            if ($this->is_first_round == true || $this->feel_free == true) {
                return [0, "你有牌權時，不能跳過！\n請重新輸入: "];
            } else {
                $this->broadCast("Player_{$this->turn} 跳過了這輪\n");
                $this->passedSum += 1;
                echo "how many people have passed? {$this->passedSum} people.\n";
                $this->turn = ($this->turn % 4) + 1; //看下一個換誰
                $currentPlayer->havePassed = true;
                $this->informNextPlayer();
                return [1, "好的，你跳過了這輪"];
            }
        }
        # 到此認為輸入為一串數字，切分數字用空格
        $numbers = preg_split('/\s+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        # 檢查是否為一串數字不能包含其他符號或文字
        if (!(count($numbers) > 0 && array_reduce($numbers, function ($carry, $item) {return $carry && is_numeric($item);}, true))) {
            return [0, "你的輸入要符合格式, 為全數字or pass\n請重新輸入: "];
        }
        //如果這次的number(Card index)使得能夠出牌，那就用這些number出牌，保存此次出牌紀錄、刪除對應的牌、保存對應的牌型
        //確認是一串數字後，檢查是否能夠出這串數字的牌
        $this->printAttemptCardInfo($fd, $currentPlayer, $numbers);
        [$status, $largestCard, $comboType, $sys_msg] = $this->checkValid($currentPlayer, $numbers, $this->feel_free, $this->previous_round_largest, $this->previous_round_type, $this->is_first_round);

        if ($status == 1) { //代表是成功的一次出牌
            echo "此人成功的出牌了\n";
            $this->feel_free = false; // 下一輪不能亂出牌
            $this->who_dominate = $this->turn; // 變成他的牌權
            $this->is_first_round = false; // 不用梅花三了
            $this->previous_round_largest = $largestCard; // 上一輪最大的牌
            $this->previous_round_type = $comboType;
            $currentPlayer->cardDeck->removeCardsByIndex($numbers); // 刪除出掉的牌
            // 檢查遊戲結束沒
            if ($this->players[0]->cardDeck->getDeckSize() == 0 || $this->players[1]->cardDeck->getDeckSize() == 0 || $this->players[2]->cardDeck->getDeckSize() == 0 || $this->players[3]->cardDeck->getDeckSize() == 0) {
                $this->isGameEnd = true;
                echo "\n\nfinish! the winner is Player_{$this->turn} !!!\n";
                $this->server->send($fd, "成功的一次出牌，你獲得了勝利 ~ ~ ~ \n");
                return [2, "遊戲結束! the winner is 玩家_{$this->turn} !!!\n"];
            } else {
                $this->turn = ($this->turn % 4) + 1; //看下一個換誰
                $this->broadCast(Config::$colorCode['CYAN'] . "\nPrevious round is using {$this->previous_round_type}, and the largest Card is : {$this->previous_round_largest->suit} {$this->previous_round_largest->number}. Player_{$this->who_dominate} has the card authority\n" . Config::$colorCode['RESET']);
                $this->informNextPlayer();
                return [1, "成功的一次出牌, 你還有{$currentPlayer->cardDeck->getDeckSize()}張手牌\n"];
            }
        } else {
            echo "\n\n失敗的一次出牌 from player_" . $from_player+1 . "\n";
            return [0, $sys_msg];
        }
    }

    //此時的turn就是下個player
    private function informNextPlayer() // 遞迴找到下一個能出牌的user
    {  //此時的turn可能早已passed, 就繼續呼叫下一個
        $currentPlayer = $this->players[$this->turn-1];
        # 檢查他有沒有pass過了 pass過就跟他說不能出, 然後直接跳過給再下一個
        if ($currentPlayer->havePassed == true) {
            echo "此Player_{$this->turn} 已經pass過 不能再出牌了\n";
            $this->server->send($this->player_fds[$this->turn-1], Config::$colorCode['RED'] . "此輪你已經pass過, 無法再出牌\n" . Config::$colorCode['RESET']);
            $this->turn = ($this->turn % 4) + 1; //看再下一個換誰
            $this->informNextPlayer();
        } else { //這個人能選擇要不要出牌 也要看他是不是最終有牌權的
            if ($this->passedSum == 3) { //如果其他人都跳過了 重置
                echo "所有人除了Player_{$this->turn}都已經pass過了\n";
                $this->broadCast("所有人除了Player_{$this->turn}都已經pass過了, 現在是他的自由牌權\n");
                $this->feel_free = true;
                //代表大家都pass過了, 初始化havePassed
                foreach ($this->players as $onePlayer) {
                    $onePlayer->havePassed = false;
                }
                $this->passedSum = 0;
            }
            $currentPlayer->cardDeck->printAllCards($this->player_fds[$this->turn-1], $this->turn);
            $this->server->send($this->player_fds[$this->turn-1], Config::$colorCode['YELLOW'] . "\nPlease enter numbers separated by whitespace: , or `pass` to skip this round\n" . Config::$colorCode['RESET']);
            if ($this->feel_free) {
                echo "The player can feel free to choose combo&card !\n";
                $this->server->send($this->player_fds[$this->turn-1], Config::$colorCode['GREEN'] . "(You can feel free to choose your combo&card !)" . Config::$colorCode['RESET']);
            }
            $this->server->send($this->player_fds[$this->turn-1], ", Your decision: ");
        }
    }

    private function checkValid($currentPlayer, $idxArr, $feel_free, $previous_round_largest, $previous_round_type, $is_first_round) //array, object array of cards
    {
        sort($idxArr);
        $comboSize = count($idxArr);
        //檢查有沒有重複牌、有沒有張數正確
        if (count(array_unique($idxArr)) != $comboSize || !in_array($comboSize, [1, 2, 5])) {
            echo "Please enter a valid set of cards\n";
            return [0, null, null, "Combo只有單張, 對子, 葫蘆, 順子, 鐵支的選擇\n請重新輸入: "];
        }
        //檢查有沒有在手牌範圍內
        if ($idxArr[0] <0 || $idxArr[count($idxArr)-1] >= $currentPlayer->cardDeck->getDeckSize()) {
            echo 'first idx:' . $idxArr[0] . ' Last idx: ' . $idxArr[count($idxArr)-1];
            echo "\nindex out of your card's range";
            return [0, null, null, "你只能輸入手牌範圍的index\n請重新輸入: "];
        }
        //檢查若是第一輪，有沒有梅花三
        if ($is_first_round == true) {
            if ($currentPlayer->cardDeck->containClubThree($idxArr) == false) {
                echo 'You must contain Club Three in the first round!';
                return [0, null, null, "第一輪中, 出牌必須包含Club Three\n請重新輸入: "];
            }
        }
        //先看牌型，再看若是自由出牌，直接檢查要出什麼
        [$nowLargest, $nowType] = $currentPlayer->cardDeck->calculateCombo($idxArr);
        if ($nowType == '無結果') {
            echo "沒有匹配的牌型，請重新出牌\n";
            return [0, null, null, "你的出牌沒有匹配的牌型\n請重新輸入: "];
        }
        echo "\nThe player's combo is " . $nowType . "\n";
        if ($feel_free) {
            return [1, $nowLargest, $nowType, "成功的出牌"];
        } elseif ($nowType == '鐵支' && $previous_round_type != '鐵支') { //鐵支最大
            return [1, $nowLargest, $nowType, "成功的出牌，無情的鐵支壓下"];
        } else { //要根據別人出的牌來出 包含鐵支之間比較
            if ($nowType != $previous_round_type) {
                echo 'you must follow the pattern: ' . $previous_round_type . "\n";
                return [0, null, null, "你得出跟上一輪一樣的牌型---{$previous_round_type}\n請重新輸入: "];
            }
            $compareResult = $this->compareBigTwo($previous_round_largest, $nowLargest); //都是Card object
            if ($compareResult < 0) {
                echo "The combo is smaller than another player!\n";
                return [0, null, null, "你的牌型比上一個人小 :(\n請重新輸入: "];
            } else {
                echo "The combo is greater than another player!\n";
                return [1, $nowLargest, $nowType, "成功的出牌"];
            }
        }
    }
    public function broadCast($word)
    {
        foreach ($this->player_fds as $onePlayerFd) {
            $this->server->send($onePlayerFd, "\nSystem Broadcast: {$word}");
        }
    }

    private function printAttemptCardInfo($fd, $currentPlayer, $idxArr) //印出使用者想出的牌
    {
        $this->server->send($fd, "\n你嘗試要出的牌有: \n");
        foreach ($currentPlayer->cardDeck->cards as $index => $oneCard) {
            if (in_array($index, $idxArr)) { //在這次準備要出的牌裡面
                $this->server->send($fd, "{$oneCard->getCardInfo()}");
            }
        }
    }

    private function compareBigTwo($card1, $card2) //用來比最大的那張的大小
    {
        $numberComparison = array_search($card1->number, $this->numberOrder) - array_search($card2->number, $this->numberOrder);
        $suitComparison = array_search($card1->suit, $this->suitOrder) - array_search($card2->suit, $this->suitOrder);

        if ($numberComparison == 0) {
            return $suitComparison;
        } else {
            return $numberComparison;
        }
    }
}
