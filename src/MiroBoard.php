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
    private bool $useAiAssist;
    private string $premiseText;
    private string $directionForRootStickers;
    private string $directionForChildStickers;
    private Logger $logger;

    const SHAPE_AICOMMENT = 'wedge_round_rectangle_callout';
    const CONTROL_PANEL_FRAME_NAME = '制御盤';
    const USE_AI_ASSIST_TEXT = '[AI支援]';
    const PREMISE_TEXT_PREFIX = '[前提]';
    const DIRECTION_FOR_ROOT_STICKERS_TEXT_PREFIX = '[最初の付箋の添削]';
    const DIRECTION_FOR_CHILD_STICKERS_TEXT_PREFIX = '[子付箋の添削]';

    public function __construct(private string $token, private string $boardId)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->logger = new Logger(Logger::DEBUG);
        $this->useAiAssist = false;
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
        $this->__loadControlPanel();
        $this->connectors = $this->__loadConnectors();
        $this->stickers = $this->__loadStickers();
        $this->aiComments = $this->__loadAiComments();
    }

    private function __loadControlPanel(): void
    {
        // get useAiAssist
        // TODO: 10件だけでなく、全件対象に
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/items?limit=10&type=frame';
        $response = $this->client->get(
            $url,
            ['headers' => $this->__headers()]
        );
        Utils::checkResponse($response, [200]);

        $controlPanelId = null;
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            // $result[$data->id] = $data;
            if ($data->data->title !== self::CONTROL_PANEL_FRAME_NAME) {
                continue;
            }
            $controlPanelId = $data->id;
            break;
        }
        if (empty($controlPanelId)) {
            throw new \Exception("Couldn't get controlPanelId");
        }

        // get directions for AI
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/items?limit=10&parent_item_id=' . $controlPanelId;
        $response = $this->client->get(
            $url,
            ['headers' => $this->__headers()]
        );

        Utils::checkResponse($response, [200]);
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            // var_dump($data);
            $text = strip_tags($data->data->content);
            if ($text === self::USE_AI_ASSIST_TEXT) {
                $this->useAiAssist = true;
            }
            if (str_starts_with($text, self::PREMISE_TEXT_PREFIX)) {
                $this->premiseText = str_replace(self::PREMISE_TEXT_PREFIX, '', $text);
            }
            if (str_starts_with($text, self::DIRECTION_FOR_ROOT_STICKERS_TEXT_PREFIX)) {
                $this->directionForRootStickers = str_replace(self::DIRECTION_FOR_ROOT_STICKERS_TEXT_PREFIX, '', $text);
            }
            if (str_starts_with($text, self::DIRECTION_FOR_CHILD_STICKERS_TEXT_PREFIX)) {
                $this->directionForChildStickers = str_replace(self::DIRECTION_FOR_CHILD_STICKERS_TEXT_PREFIX, '', $text);
            }
        }
    }

    private function __loadStickers(): array
    {
        // TODO: 10件だけでなく、全件対象に
        $url = 'https://api.miro.com/v2/boards/' . urlencode($this->boardId) . '/items?limit=10&type=sticky_note';
        $response = $this->client->get(
            $url,
            ['headers' => $this->__headers()]
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
            ['headers' => $this->__headers()]
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
            ['headers' => $this->__headers()]
        );
        Utils::checkResponse($response, [200]);

        $result = [];
        var_dump($this->stickers);
        foreach (json_decode((string)$response->getBody(), false)->data as $data) {
            if (isset($data->data->shape) && !in_array($data->data->shape, [self::SHAPE_AICOMMENT])) {
                continue;
            }
            $miroComment = new MiroComment($data);
            [$bindedType, $bindedId] = MiroComment::extractBinded($miroComment->getText());
            if ($bindedType == MiroComment::BINDED_TYPE_STICKER) {
                $miroComment->setSticker($this->stickers[$bindedId]);
            } elseif ($bindedType == MiroComment::BINDED_TYPE_CONNECTOR) {
                $miroComment->setConnector($this->connectors[$bindedId]);
            } else {
                throw new \Exception("Could not get binded item for:\n" . $miroComment->getText());
                // $this->logger("Skipping ")
            }   
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
        $comment = MiroComment::getBindedCommentToSticker($comment, $sticker->getMiroId());
        $data = $this->__putComment($sticker, $comment, $sticker->getPosition()["x"] + 110, $sticker->getPosition()["y"] - 100);
        $miroComment = new MiroComment($data);
        $miroComment->setSticker($sticker);
    }

    public function putCommentToConnector(MiroConnector $connector, string $comment): void
    {
        $comment = MiroComment::getBindedCommentToConnector($comment, $connector->getMiroId());
        // $startItem = $this->stickers[$connector->getStartItemId()];
        $endItem = $this->stickers[$connector->getEndItemId()];
        $data = $this->__putComment(
            $connector,
            $comment,
            // ($startItem->getPosition()["x"] + $endItem->getPosition()["x"]) / 2 + 100,
            // ($startItem->getPosition()["y"] + $endItem->getPosition()["y"]) / 2 - 50
            $endItem->getPosition()["x"] + 100,
            $endItem->getPosition()["y"] - 100
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

    public function useAiAssist(): bool
    {
        return $this->useAiAssist;
    }

    public function getPremiseText(): string
    {
        return $this->premiseText;
    }
    public function getDirectionForRootStickers(): string
    {
        return $this->directionForRootStickers;
    }
    public function getDirectionForChildStickers(): string
    {
        return $this->directionForChildStickers;
    }
}
