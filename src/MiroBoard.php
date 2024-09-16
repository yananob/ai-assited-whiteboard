<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\Utils;
use MyApp\MiroSticker;
use MyApp\MiroConnector;
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

    const SHAPE_AICOMMENT = 'wedge_round_rectangle_callout';

    public function __construct(private string $token, private string $boardId)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = new Logger(Logger::DEBUG);
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
        $this->connectors = $this->__loadConnectors();
        $this->stickers = $this->__loadStickers();
        $this->aiComments = $this->__loadAiComments();
    }

    private function __loadStickers(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/items?limit=10&type=sticky_note';
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            // $result[$data->id] = $data;
            $miroSticker = new MiroSticker($data, $this->connectors);
            $result[$data->id] = $miroSticker;
        }
        return $result;
    }

    private function __loadConnectors(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/connectors?limit=10';
        $response = $this->client->get(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            // $result[$data->id] = $data;
            $miroConnector = new MiroConnector($data);
            $result[$data->id] = $miroConnector;
        }
        return $result;
    }

    private function __loadAiComments(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/items?limit=10&type=shape';
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
                continue;
            }
            $miroComment = new MiroComment($data);
            $stickerId = MiroComment::extractStickerId($miroComment->getText());
            $miroComment->setSticker($this->stickers[$stickerId]);
            $result[$data->id] = $miroComment;
        }
        return $result;
    }

    public function getAiComments(): array
    {
        return $this->aiComments;
    }

    public function getRecentRootStickers(int $count = 5): array
    {
        $result = [];
        $stickers = $this->stickers;
        usort($stickers, [MiroBoard::class, '__compareDate']);
        foreach ($stickers as $sticker) {
            if ($sticker->hasParentStickers()) {
                continue;
            }
            $result[] = $sticker;
            if (count($result) >= $count) {
                return $result;
            }
        }
        return $result;
    }

    public function getRecentConnectors(int $count = 5): array
    {
        $data = $this->connectors;
        usort($data, [MiroBoard::class, '__compareDate']);
        return array_slice($data, 0, $count);
    }

    private function __compareDate($a, $b)
    {
        if ($a->getModifiedAt() == $b->getModifiedAt()) {
            return 0;
        }
        return ($a->getModifiedAt() > $b->getModifiedAt()) ? -1 : 1;      // MEMO: 更新日時の降順
    }

    public function getStickerText(string $id): string
    {
        return strip_tags($this->stickers[$id]->getText());
    }

    private function __putComment($targetItem, string $comment, float $x, float $y): object
    {
        $body = [
            'data' => [
                'content' => $comment,
                'shape' => self::SHAPE_AICOMMENT,
            ],
            'style' => ['borderColor' => '#1a1a1a', 'borderOpacity' => '0.7', 'fillOpacity' => '0.7', 'fillColor' => '#ffffff', 'fontSize' => 12],
            'position' => ['x' => $x, 'y' => $y],
            'geometry' => ['height' => 150, 'width' => 250],
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

    public function putCommentToSticker(MiroSticker $sticker, string $comment): void
    {
        $comment = MiroComment::bindMiroId($comment, $sticker->getMiroId());
        $data = $this->__putComment($sticker, $comment, $sticker->getPosition()["x"] + 110, $sticker->getPosition()["y"] - 80);
        $miroComment = new MiroComment($data);
        $miroComment->setSticker($sticker);
    }

    public function putCommentToConnector(MiroConnector $connector, string $comment): void
    {
        $comment = MiroComment::bindMiroId($comment, $connector->getMiroId());
        $startItem = $this->stickers[$connector->getStartItemId()];
        $endItem = $this->stickers[$connector->getEndItemId()];
        $data = $this->__putComment(
            $connector,
            $comment,
            ($startItem->getPosition()["x"] + $endItem->getPosition()["x"]) / 2 + 100,
            ($startItem->getPosition()["y"] + $endItem->getPosition()["y"]) / 2 - 50
        );
        $miroComment = new MiroComment($data);
        $miroComment->setConnector($connector);
    }

    private function __deleteShape(string $shapeId): void
    {
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/shapes/' . $shapeId;
        $response = $this->client->delete(
            $url,
            [
                'headers' => $this->__headers(),
            ]
        );
        Utils::checkResponse($response, [204]);
    }

    public function clearAiCommentsForModifiedItems(): void
    {
        foreach ($this->aiComments as $miroComment) {
            $this->logger->debug('checking modified for ' . $miroComment->getText());
            // var_dump($miroComment);
            if ($miroComment->isBindedItemModified()) {
                $this->logger->debug('deleting old comment: ' . $miroComment->getMiroId());
                $this->__deleteShape($miroComment->getMiroId());
                unset($this->aiComments[$miroComment->getMiroId()]);
            }
        }
    }
}
