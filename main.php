<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\MiroBoard;
use MyApp\Gpt;
use MyApp\AiComment;

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

// function getCommentForConnector(MiroBoard $miroBoard, Gpt $gpt, $miroConnector): string
// {
//     $text = "「" . $miroBoard->getStickerText($miroConnector->startItem->id) . "」ことの" .
//         strip_tags($miroConnector->captions[0]->content) . "は" .
//         "「" . $miroBoard->getStickerText($miroConnector->endItem->id) . "」である。";
//     $message = <<<EOS
// 文章『{$text}』を以下の基準で評価してください。
// ・文章が表す関係が適切で、整合性が取れているか

// 文章に問題がない場合は「ない」の2文字を、問題がある場合はフィードバックを100文字以内でください。
// EOS;
//     return $gpt->callChatApi("You are a helpful assistant.", $message);
// }

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
        $miroBoard->refresh();

        $miroBoard->clearCommentsForModifiedStickers();
        // $miroBoard->clearCommentsForUpdatedStickers();

        // TODO: miro apiレスポンスのデータ構造に依存しすぎ
        foreach ($miroBoard->getRecentItems(2) as $miroItem) {
            $logger->info("Processing miroItem: {$miroItem->data->content}");

            if ($miroBoard->hasAiComment($miroItem)) {
                $logger->info("aiComment exists, skipping", 1);
                continue;
            }

            $aiComment = AiComment::createFromText(getCommentForSticker($gpt, $miroItem->data->content));
            $aiComment->setSticker($miroItem);
            $logger->info("Comment from gpt: {$aiComment->getText()}", 1);

            if ($aiComment->getText() === COMMENT_NONE) {
                continue;
            }
            $miroBoard->putCommentToSticker($miroItem, $aiComment);
        }

        // foreach ($miroBoard->getRecentConnectors(2) as $miroConnector) {
        //     $logger->info("Processing miroConnector: {$miroConnector->captions[0]->content}");

        //     $comment = getCommentForConnector($miroBoard, $gpt, $miroConnector);
        //     $logger->info("Comment from gpt: {$comment}", 1);

        //     if ($comment === COMMENT_NONE) {
        //         continue;
        //     }
        //     $miroBoard->putCommentToConnector($miroConnector, $comment);
        // }

        // $logger->info("Sleeping");
        // sleep(10);
        break;  // DEBUG
    }
}

main();
