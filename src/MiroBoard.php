<?php

namespace MyApp;

use MyApp\Logger;

class MiroBoard
{
    private \GuzzleHttp\Client $client;
    private Logger $logger;

    public function __construct(private string $secret, private string $boardId)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = new Logger();
    }

    public function readRecentItems(int $count = 5): array
    {
        $response = $this->client->request(
            'GET',
            "https://api.miro.com/v2/boards/{$this->boardId}/items?limit=10&type=sticky_note",
            [
                'headers' => [
                    'accept' => 'application/json',
                    'authorization' => "Bearer {$this->secret}",
                ],
            ]
        );

        if ($response->getStatusCode() != 200) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
        $data = $response->getBody()["data"];
        $this->logger->info("data 1");
        $this->logger->info($data);
        usort($data, '$this->__compareDate');
        $this->logger->info("data 2");
        $this->logger->info($data);
        $data = array_slice($data, 0, $count);
        $this->logger->info("data 3");
        $this->logger->info($data);

        return $data;
    }

    private function __compareDate($a, $b)
    {
        if ($a["modifiedAt"] == $b["modifiedAt"]) {
            return 0;
        }
        return ($a["modifiedAt"] < $b["modifiedAt"]) ? -1 : 1;
    }
}
