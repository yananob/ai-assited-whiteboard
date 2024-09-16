<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\Gpt;
use MyApp\MiroBoard;
use MyApp\MiroConnector;

const COMMENT_NONE = "ない";

function getCommentForSticker(Gpt $gpt, string $text): string
{
    $text = strip_tags($text);
    $message = <<<EOS
文章『{$text}』を以下の基準で評価してください。
・具体的か
・誤解を生む表現でないか

文章に問題がない場合は「ない」の2文字を、問題がある場合はフィードバックを100文字以内でください。
EOS;
    return $gpt->callChatApi("You are a helpful assistant.", $message);
}

function getCommentForConnector(MiroBoard $miroBoard, Gpt $gpt, MiroConnector $miroConnector): string
{
    $text = "「" . $miroBoard->getStickerText($miroConnector->getStartItemId()) . "」ことの" .
        strip_tags($miroConnector->getText()) . "は" .
        "「" . $miroBoard->getStickerText($miroConnector->getEndItemId()) . "」である。";
    $message = <<<EOS
文章『{$text}』を以下の基準で評価してください。
・文章が表す関係が適切で、整合性が取れているか

文章に問題がない場合は「ない」の2文字を、問題がある場合はフィードバックを100文字以内でください。
EOS;
    return $gpt->callChatApi("You are a helpful assistant.", $message);
}


function main()
{
    $logger = new Logger();
    $config = json_decode(file_get_contents("./configs/config.json"), false);
    if ($config === false) {
        throw new Exception("Failed to load config file");
    }

    $miroBoard = new MiroBoard($config->miro->token, $config->miro->board_id);
    $gpt = new Gpt($config->gpt->secret, $config->gpt->model);

    $max_loop = 5;
    while ($max_loop-- > 0) {
        $miroBoard->refresh();

        $miroBoard->clearAiCommentsForModifiedItems();

        foreach ($miroBoard->getRecentRootStickers(5) as $sticker) {
            $logger->info("Processing miroItem: {$sticker->getText()}");

            if ($sticker->hasAiComment($miroBoard->getAiComments())) {
                $logger->info("MiroComment exists, skipping", 1);
                continue;
            }

            $comment = getCommentForSticker($gpt, $sticker->getText());
            $logger->info("Comment from gpt: {$comment}", 1);

            if ($comment === COMMENT_NONE) {
                continue;
            }
            $miroBoard->putCommentToSticker($sticker, $comment);
        }

        foreach ($miroBoard->getRecentConnectors(5) as $miroConnector) {
            $logger->info("Processing miroConnector: {$miroConnector->getText()}");

            $comment = getCommentForConnector($miroBoard, $gpt, $miroConnector);
            $logger->info("Comment from gpt: {$comment}", 1);

            if ($comment === COMMENT_NONE) {
                continue;
            }
            $miroBoard->putCommentToConnector($miroConnector, $comment);
        }

        break;      // DEBUB
        // $logger->info("Sleeping");
        // sleep(10);
    }
}

main();
