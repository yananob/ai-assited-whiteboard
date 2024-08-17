<?php

namespace MyApp;

class Logger
{

    function info(string|array $text): void
    {
        if (gettype($text) == "array") {
            $text = implode(", ", $text);
        }
        echo $text . "\n";
    }
}
