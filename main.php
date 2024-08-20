<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use MyApp\Logger;
use MyApp\MiroBoard;
use MyApp\Gpt;

function getCommentForSticker(Gpt $gpt, string $text): string
{
    $message = <<<EOS
文章「{$text}」を以下の基準で評価してください。
・具体的か
・誤解を生む表現でないか

文章に問題がない場合は「ない」の2文字を、問題がある場合はフィードバックを30文字以内でください。
EOS;
    return $gpt->callChatApi("You are a helpful assistant.", $message);
}

function getCommentForConnector(MiroBoard $miroBoard, Gpt $gpt, $miroConnector): string
{
    $text = "「" . $miroBoard->getStickerText($miroConnector->startItem->id) . "」ことの" .
        $miroConnector->captions[0]->content . "は" .
        "「" . $miroBoard->getStickerText($miroConnector->endItem->id) . "」である。";
    $message = <<<EOS
文章「{$text}」を以下の基準で評価してください。
【矢印元】と【矢印先】の関係を以下の基準で評価してください。
・関係が【矢印テキスト】の通りになっているか。矛盾がないか

文章に問題がない場合は「ない」の2文字を、問題がある場合はフィードバックを30文字以内でください。
EOS;
    return $gpt->callChatApi(
        "You are a helpful assistant.",
        $text . "へのメッセージを、50文字以内で下さい。"
    );
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

    while (true) {
        $miroBoard->refresh();

        foreach ($miroBoard->getRecentItems(3) as $miroItem) {
            $logger->info("Processing miroItem: {$miroItem->data->content}");

            $comment = getCommentForSticker($gpt, $miroItem->data->content);
            $logger->info("Comment from gpt: {$comment}", 1);

            $miroBoard->putCommentToSticker($miroItem, $comment);
        }

        foreach ($miroBoard->getRecentConnectors(3) as $miroConnector) {
            $logger->info("Processing miroConnector: {$miroConnector->captions[0]->content}");

            $comment = getCommentForConnector($miroBoard, $gpt, $miroConnector);
            $logger->info("Comment from gpt: {$comment}", 1);

            $miroBoard->putCommentToConnector($miroConnector, $comment);
        }

        // $logger->info("Sleeping");
        // sleep(10);
        break;  // DEBUG
    }
}

main();
