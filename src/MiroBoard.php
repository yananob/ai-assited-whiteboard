<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\Utils;
use MyApp\AiComment;

class MiroBoard
{
    private \GuzzleHttp\Client $client;
    private array $stickers;
    private array $connectors;
    private array $aiComments;

    public function __construct(private string $token, private string $boardId)
    {
        $this->client = new \GuzzleHttp\Client();
    }

    private function __headers(): array
    {
        return [
            'accept' => 'application/json',
            'authorization' => "Bearer {$this->token}",
            'content-type' => 'application/json',
        ];
    }

    public function refresh(): void
    {
        $this->stickers = $this->__loadStickers();
        $this->connectors = $this->__loadConnectors();
        $this->aiComments = $this->__loadAiComments();
    }

    private function __loadStickers(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = "https://api.miro.com/v2/boards/" . urlencode($this->boardId) . "/items?limit=10&type=sticky_note";
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            $result[$data->id] = $data;
        }
        return $result;
    }

    private function __loadConnectors(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = "https://api.miro.com/v2/boards/" . urlencode($this->boardId) . "/connectors?limit=10";
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            $result[$data->id] = $data;
        }
        return $result;
    }

    private function __loadAiComments(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = "https://api.miro.com/v2/boards/" . urlencode($this->boardId) . "/items?limit=10&type=shape";
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            if (isset($data->data->shape) && !in_array($data->data->shape, ['wedge_round_rectangle_callout'])) {
                // echo "[DEBUG] skipping " . $data->type . "\n";
                continue;
            }
            // echo str_repeat("-", 80) . "\n";
            // var_dump($data);
            $aiComment = AiComment::createFromShape($data);
            $aiComment->setSticker($this->stickers[$aiComment->getStickerId()]);
            // var_dump($aiComment);
            $result[$data->id] = $aiComment;
        }
        return $result;
    }

    public function getRecentItems(int $count = 5): array
    {
        $data = $this->stickers;
        usort($data, [MiroBoard::class, "__compareDate"]);
        return array_slice($data, 0, $count);
    }

    public function getRecentConnectors(int $count = 5): array
    {
        $data = $this->connectors;
        usort($data, [MiroBoard::class, "__compareDate"]);
        return array_slice($data, 0, $count);
    }

    private function __compareDate($a, $b)
    {
        if ($a->modifiedAt == $b->modifiedAt) {
            return 0;
        }
        return ($a->modifiedAt > $b->modifiedAt) ? -1 : 1;
    }

    public function getStickerText(string $id): string
    {
        return strip_tags($this->stickers[$id]->data->content);
    }

    private function __putComment($targetItem, string $comment, float $x, float $y): void
    {
        $body = [
            "data" => [
                "content" => $comment,
                "shape" => "wedge_round_rectangle_callout",
            ],
            "style" => ["borderColor" => "#1a1a1a", "borderOpacity" => "0.7", "fillOpacity" => "0.7", "fillColor" => "#ffffff"],
            "position" => ["x" => $x, "y" => $y],
            "geometry" => ["height" => 100, "width" => 200],
        ];

        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/shapes';
        $response = $this->client->post(
            $url,
            [
                'body' => json_encode($body),
                'headers' => $this->__headers(),
            ]
        );

        Utils::checkResponse($response, [201]);
    }

    public function putCommentToSticker($sticker, AiComment $aiComment): void
    {
        // $comment = $this->__bindToSticker($sticker, $comment);
        // $aiComment->bindToSticker($sticker);
        $this->__putComment($sticker, $aiComment->getShapeText(), $sticker->position->x + 100, $sticker->position->y - 50);
    }

    // public function putCommentToConnector($connector, string $comment): void
    // {
    //     $comment = $this->__bindToConnector($connector, $comment);
    //     $this->__putComment(
    //         $connector,
    //         $aiComment->comment,
    //         ($this->stickers[$connector->startItem->id]->position->x + $this->stickers[$connector->endItem->id]->position->x) / 2 + 100,
    //         ($this->stickers[$connector->startItem->id]->position->y + $this->stickers[$connector->endItem->id]->position->y) / 2 - 50
    //     );
    // }

    // private function __bindToSticker($sticker, string $comment): string
    // {
    //     return $comment . "\n[" . $sticker->id . "]";
    // }

    // private function __bindToConnector($connector, string $comment): string
    // {
    //     return $comment . "\n[" . $connector->id . "]";
    // }

    public function clearCommentsForModifiedStickers(): void
    {
        $logger = new Logger();
        foreach ($this->aiComments as $aiComment) {
            $logger->info("checking modified for " . $aiComment->getText());
            // var_dump($aiComment);
            if ($aiComment->isStickerModified()) {
                $this->__deleteShape($aiComment->getMiroId());

                $logger->info("deleting old comment: " . $aiComment->getMiroId());

                unset($this->aiComments[$aiComment->getMiroId()]);
            }
        }
    }

    private function __deleteShape(string $shapeId): void
    {
        $url = "https://api.miro.com/v2/boards/" . urlencode($this->boardId) . "/shapes/" . $shapeId;
        $response = $this->client->delete(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [204]);
    }

    public function hasAiComment($sticker): bool
    {
        foreach ($this->aiComments as $aiComment) {
            if ($sticker->id === $aiComment->getStickerId()) {
                return true;
            }
        }
        return false;
    }

    // public function clearCommentsForUpdatedConnectors(): void {}
}
