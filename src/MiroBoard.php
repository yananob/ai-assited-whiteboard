<?php

namespace MyApp;

use MyApp\Logger;

class MiroBoard
{
    private \GuzzleHttp\Client $client;
    private Logger $logger;

    public function __construct(private string $token, private string $boardId)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = new Logger();
    }

    private function __headers(): array
    {
        return [
            'accept' => 'application/json',
            'authorization' => "Bearer {$this->token}",
            'content-type' => 'application/json',
        ];
    }

    public function readRecentItems(int $count = 5): array
    {
        $url = "https://api.miro.com/v2/boards/" . urlencode($this->boardId) . "/items?limit=10&type=sticky_note";
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
                // 'headers' => [
                //     'accept' => 'application/json',
                //     'authorization' => "Bearer {$this->token}",
                // ]
            ]
        );

        if ($response->getStatusCode() != 200) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
        $data = json_decode((string)$response->getBody(), false)->data;
        $this->logger->info("data 1");
        $this->logger->info($data);
        usort($data, [MiroBoard::class, "__compareDate"]);
        $this->logger->info("data 2");
        $this->logger->info($data);
        $data = array_slice($data, 0, $count);
        $this->logger->info("data 3");
        $this->logger->info($data);

        return $data;
    }

    private function __compareDate($a, $b)
    {
        if ($a->modifiedAt == $b->modifiedAt) {
            return 0;
        }
        return ($a->modifiedAt < $b->modifiedAt) ? -1 : 1;
    }

    public function putComment($parentItem, string $comment): void
    {
        $body = [
            "data" => [
                "content" => "COMMENT",
                "shape" => "rectangle",
            ],
            "position" => [
                "x" => 10,
                "y" => 20,
            ],
            "parent" => [
                "id" => "PARENT_ID",
            ],
        ];

        $response = $this->client->post(
            'https://api.miro.com/v2/boards/board_id/shapes',
            [
                'body' => json_encode($body),
                'headers' => $this->__headers(),
            ]
        );

        if ($response->getStatusCode() != 200) {
            throw new \Exception("Request error: [{$response->getStatusCode()} {$response->getReasonPhrase()}");
        }
    }
}
