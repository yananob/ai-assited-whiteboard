<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\Utils;
use MyApp\MiroComment;

/**
 * Miroボードレベルクラス
 */
class MiroBoard
{
    private \GuzzleHttp\Client $client;
    private array $stickers;
    private array $connectors;
    private array $aiComments;
    private Logger $logger;

    const SHAPE_AICOMMENT = "wedge_round_rectangle_callout";

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
            if (isset($data->data->shape) && !in_array($data->data->shape, [self::SHAPE_AICOMMENT])) {
                // echo "[DEBUG] skipping " . $data->type . "\n";
                continue;
            }
            // echo str_repeat("-", 80) . "\n";
            // var_dump($data);
            $miroComment = new MiroComment($data);
            $stickerId = MiroComment::extractStickerId($miroComment->getText());
            $miroComment->setSticker($this->stickers[$stickerId]);
            // var_dump($miroComment);
            $result[$data->id] = $miroComment;
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

    private function __putComment($targetItem, string $comment, float $x, float $y): object
    {
        $body = [
            "data" => [
                "content" => $comment,
                "shape" => self::SHAPE_AICOMMENT,
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
        return json_decode((string)$response->getBody(), false);
    }

    public function putCommentToSticker($sticker, string $comment): void
    {
        // $comment = $this->__bindToSticker($sticker, $comment);
        // $miroComment->bindToSticker($sticker);
        $data = $this->__putComment($sticker, $comment, $sticker->position->x + 100, $sticker->position->y - 50);
        $miroComment = new MiroComment($data);
        $miroComment->setSticker($sticker);
    }

    // public function putCommentToConnector($connector, string $comment): void
    // {
    //     $comment = $this->__bindToConnector($connector, $comment);
    //     $this->__putComment(
    //         $connector,
    //         $miroComment->comment,
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

    public function clearAiCommentsForModifiedStickers(): void
    {
        foreach ($this->aiComments as $miroComment) {
            $this->logger->debug("checking modified for " . $miroComment->getText());
            // var_dump($miroComment);
            if ($miroComment->isStickerModified()) {
                $this->logger->debug("deleting old comment: " . $miroComment->getMiroId());
                $this->__deleteShape($miroComment->getMiroId());
                unset($this->aiComments[$miroComment->getMiroId()]);
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
        foreach ($this->aiComments as $miroComment) {
            if ($sticker->id === $miroComment->getStickerId()) {
                return true;
            }
        }
        return false;
    }

    // public function clearCommentsForUpdatedConnectors(): void {}
}
