<?php

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\MiroBoard;
use MyApp\Gpt;

function main()
{
    $logger = new Logger();

    $config = json_decode(file_get_contents("./configs/config.json"), false);
    if ($config === false) {
        throw new Exception("Failed to load config file");
    }

    $miroBoard = new MiroBoard($config->miro->token, $config->miro->board_id);
    $gpt = new Gpt($config->gpt->secret, $config->gpt->model);

    while (true) {
        $miroItems = $miroBoard->readRecentItems(5);

        foreach ($miroItems as $miroItem) {
            $logger->info("processing miroItem: {$miroItem->data->content}");

            $comment = $gpt->getComment($miroItem->data->content);
            $logger->info("comment from gpt: {$comment}");

            $miroBoard->putComment($miroItem, $comment);
        }

        $logger->info("sleeping");
        sleep(10);
    }
}

main();
