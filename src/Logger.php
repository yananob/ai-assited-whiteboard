<?php

namespace MyApp;

class Logger
{

    function info(string|array $message): void
    {
        if (is_null($message)) {
            $message = "";
        } else if (in_array(gettype($message), ["array", "object"])) {
            $message = json_encode($message);
        }
        echo $message . "\n";
    }
}
