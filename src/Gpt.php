<?php

namespace MyApp;

class Gpt
{
    private \GuzzleHttp\Client $client;
    private Logger $logger;

    public function __construct(private string $secret, private string $model)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = new Logger();
    }

    function getComment(string $source): string
    {
        $response = $this->client->post(
            "https://api.openai.com/v1/chat/completions",
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$this->secret}",
                ],
                'data' => $source,
            ]
        );

        if ($response->getStatusCode() != 200) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
        $data = $response->getBody();
        $this->logger->info($data);

        $answer = $data["choices"][0]["message"]["content"];
        $this->logger->info($answer);

        return $answer;
    }
}
