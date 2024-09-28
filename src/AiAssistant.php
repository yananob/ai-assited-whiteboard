<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\Utils;

/**
 * GPT処理ラッパー
 */
class AiAssistant
{
    private \GuzzleHttp\Client $client;

    const BLANK_RESPONSE = 'NOTHING';

    public function __construct(
        private string $secret,
        private string $model,
        private string $premiseText,
        private string $directionForRootStickers,
        private string $directionForChildStickers
    ) {
        $this->client = new \GuzzleHttp\Client();
    }

    private function __filterResult(string $comment): ?string
    {
        return str_contains($comment, self::BLANK_RESPONSE) ? null : $comment;
    }

    public function getCommentForRootSticker(string $stickerText): ?string
    {
        $message = str_replace("{TEXT}", $stickerText, $this->directionForRootStickers);
        return $this->__filterResult($this->__callChatApi($this->premiseText,  $message));
    }

    public function getCommentForConnector(string $sticker1Text, string $relationText, string $sticker2Text): ?string
    {
        $message = $this->directionForChildStickers;
        $message = str_replace('{TEXT1}', $sticker1Text, $message);
        $message = str_replace('{RELATION}', $relationText, $message);
        $message = str_replace('{TEXT2}', $sticker2Text, $message);
        return $this->__filterResult($this->__callChatApi($this->premiseText,  $message));
    }

    private function __callChatApi(string $context, string $message): string
    {
        $logger = new Logger();
        $logger->info("Calling ChatApi: [{$context}] <{$message}>", 1);

        $payload = [
            "model" => $this->model,
            "messages" => [
                [
                    "role" => "system",
                    "content" => $context,
                ],
                [
                    "role" => "user",
                    "content" => $message,
                ],
            ],
        ];

        $response = $this->client->post(
            "https://api.openai.com/v1/chat/completions",
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->secret}",
                ],
                'body' => json_encode($payload),
            ]
        );

        Utils::checkResponse($response, [200]);
        $data = json_decode((string)$response->getBody(), false);
        $answer = $data->choices[0]->message->content;

        return $answer;
    }
}
