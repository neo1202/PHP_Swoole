# PhP_Swoole
使用php 結合swoole框架實作能夠供多人連線即時對戰的大老二（BigTwo)遊戲。能夠透過Telnet或是client連接到Swoole TCPserver
使用coroutine來使client能夠隨時發送訊息給TCPserver並隨時準備接收回傳的訊息

run composer dump-autoload -o
//can generate autoload.php 來達成對於各個Object Namespace的import
