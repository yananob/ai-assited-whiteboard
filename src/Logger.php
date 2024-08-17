<?php

declare(strict_types=1);

namespace MyApp;

class Logger
{

    function info(string|array|null $message, int $indent = 0): void
    {
        if (is_null($message)) {
            $message = "";
        } else if (in_array(gettype($message), ["array", "object"])) {
            $message = json_encode($message);
        }
        echo str_repeat(" ", $indent * 2) . $message . "\n";
    }
}
