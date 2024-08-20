<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\Utils;

class Gpt
{
    private \GuzzleHttp\Client $client;

    public function __construct(private string $secret, private string $model)
    {
        $this->client = new \GuzzleHttp\Client();
    }

    public function callChatApi(string $context, string $message): string
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
