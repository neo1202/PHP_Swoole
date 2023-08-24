<h3>使用php 結合swoole框架實作能夠供多人連線即時對戰的大老二（BigTwo)遊戲。</h3>
<i>~無前端畫面~</i>
<br>

-------

使用Room, ready, Game的配桌概念, 連線時自動匹配到尚未開始\人數有缺的房間

包含各式Error Handling來阻擋不合法、非輪次的出牌

能夠透過Telnet或是client連接到Swoole TCPserver
使用coroutine來使client能夠隨時同步發送訊息給TCPserver並準備接收回傳的訊息

Server也能主動使用BroadCast廣播給Room內所有玩家遊戲進行情況

run composer dump-autoload -o
//can generate autoload.php 來達成對於各個Object Namespace之間的import


