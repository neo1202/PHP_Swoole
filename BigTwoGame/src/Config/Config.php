<?php

//composer dump-autoload -o
//can generate autoload.php

namespace MyApp\Config;

class Config
{
    public static $config = [
        'suitOrder' => ['spade', 'heart', 'diamond', 'club'],
        'numberOrder' => [2, 1, 13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3],
    ];
    public static $colorCode = [
        'RESET' => "\033[0m",
        'YELLOW' => "\033[33m",
        'GREEN' => "\033[32m",
        'CYAN' => "\033[36m",
        'BOLD' => "\033[1m",
        'RED' => "\033[31m",
    ];
}
