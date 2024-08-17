<?php

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\MiroBoard;
use MyApp\Gpt;

function main()
{
    $logger = new Logger();

    $config = file_get_contents("./configs/config.json");

    $miroBoard = new MiroBoard($config["miro"]["secret"], $config["miro"]["board_id"]);
    $gpt = new Gpt($config["gpt"]["secret"], $config["gpt"]["model"]);

    while (true) {
        $miroItems = $miroBoard->readRecentItems(5);

        foreach ($miroItems as $miroItem) {
            $logger->info("processing miroItem: {$miroItem->text}");

            $comment = $gpt->getComment($miroItem->text);
            $logger->info("comment from gpt: {$comment}");

            $miroBoard->putComment($miroItem, $comment);
        }

        $logger->info("sleeping");
        sleep(10);
    }
}

main();
