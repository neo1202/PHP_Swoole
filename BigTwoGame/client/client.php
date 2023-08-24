<?php

//https://wiki.swoole.com/#/coroutine_client/client
//https://blog.51cto.com/zhangyue0503/5713607 邊界問題EOF
use Swoole\Coroutine\Client;

use function Swoole\Coroutine\run;

run(function () {
    $client = new Client(SWOOLE_SOCK_TCP);
    if (!$client->connect('127.0.0.1', 9501, 0.5)) {
        echo "connect failed. Error: {$client->errCode}\n";
    }
    //$client->send("hello world\n");
    go(function () use ($client) {
        while (true) {
            $message = fgets(STDIN); // Read input from terminal
            $client->send($message); // Send the input to the server
        }
    });
    while (true) {
        $data = $client->recv();
        if (strlen($data) > 0) {
            echo $data;
            //$client->send(time() . PHP_EOL);
            if ($data == 'close') {
                echo "OH, server tells me that I should close this client!\n";
                $client->close();
                break;
            }
        }
        // \Co::sleep(1);
    }
});
