<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\AiAssistant;
use MyApp\MiroBoard;

const MAX_ITEMS_FOR_COMMENT = 3;

function main()
{
    $logger = new Logger();
    $config = json_decode(file_get_contents("./configs/config.json"), false);
    if ($config === false) {
        throw new Exception("Failed to load config file");
    }

    $miroBoard = new MiroBoard($config->miro->token, $config->miro->board_id);

    $max_loop = 5;
    while ($max_loop-- > 0) {
        $miroBoard->refresh();
        if (!$miroBoard->useAiAssist()) {
            $logger->info("not using AiAssist, exiting");
            continue;
        }

        $assistant = new AiAssistant(
            $config->gpt->secret,
            $config->gpt->model,
            $miroBoard->getPremiseText(),
            $miroBoard->getDirectionForRootStickers(),
            $miroBoard->getDirectionForChildStickers()
        );

        $miroBoard->clearAiCommentsForModifiedItems();

        foreach ($miroBoard->getRecentRootStickers(MAX_ITEMS_FOR_COMMENT) as $sticker) {
            $logger->info("Processing miroItem: {$sticker->getText()}");

            if ($sticker->hasAiComment($miroBoard->getAiComments())) {
                $logger->info("MiroComment exists, skipping", 1);
                continue;
            }

            $miroBoard->putThinkingCommentToSticker($sticker);
            $comment = $assistant->getCommentForRootSticker($sticker->getText());
            $logger->info("Comment for stickers from GPT: {$comment}", 1);
            $miroBoard->deleteBindedComment($sticker);
            if (empty($comment)) {
                continue;
            }
            $miroBoard->putCommentToSticker($sticker, $comment);
        }

        foreach ($miroBoard->getRecentConnectors(MAX_ITEMS_FOR_COMMENT) as $miroConnector) {
            $logger->info("Processing miroConnector: {$miroConnector->getText()}");

            if ($miroConnector->hasAiComment($miroBoard->getAiComments())) {
                $logger->info("MiroComment exists, skipping", 1);
                continue;
            }

            $miroBoard->putThinkingCommentToConnector($miroConnector);
            $comment = $assistant->getCommentForConnector(
                $miroBoard->getStickerText($miroConnector->getStartItemId()),
                $miroConnector->getText(),
                $miroBoard->getStickerText($miroConnector->getEndItemId())
            );
            $logger->info("Comment for connectors from GPT: {$comment}", 1);
            $miroBoard->deleteBindedComment($miroConnector);
            if (empty($comment)) {
                continue;
            }
            $miroBoard->putCommentToConnector($miroConnector, $comment);
        }

        // break;      // DEBUG
        $logger->info("Sleeping");
        sleep(10);
    }
}

main();
