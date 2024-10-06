<?php

declare(strict_types=1);

namespace MyApp;

class Logger
{
    const DEBUG = 1;
    const INFO = 2;

    public function __construct(private int $level = self::INFO) {}

    private function __output(int $level, string|array|object|null $message, int $indent = 0): void
    {
        if ($level < $this->level) {
            return;
        }

        if (is_null($message)) {
            $message = "";
        } else if (in_array(gettype($message), ["array", "object"], true)) {
            $message = json_encode($message);
        }
        echo str_repeat(" ", $indent * 2) . $message . "\n";
    }

    public function debug(string|array|object|null $message, int $indent = 0): void
    {
        $this->__output(self::DEBUG, $message, $indent);
    }

    public function info(string|array|object|null $message, int $indent = 0): void
    {
        $this->__output(self::INFO, $message, $indent);
    }
}
