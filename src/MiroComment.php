<?php

declare(strict_types=1);

namespace MyApp;

use MyApp\MiroSticker;

/**
 * Miro上のコメントオブジェクト
 */
class MiroComment
{
    private MiroSticker $sticker;
    private MiroConnector $connector;
    private string $shapeText;

    const BINDED_TYPE_STICKER = 'ST';
    const BINDED_TYPE_CONNECTOR = 'CN';

    public function __construct(private $miroItem)
    {
        $this->shapeText = $miroItem->data->content;
    }

    public function getMiroId(): string
    {
        return $this->miroItem->id;
    }

    public function getText(): string
    {
        return $this->shapeText;
    }

    public static function extractBinded(string $shapeText): ?array
    {
        preg_match('/\[([A-Z]+?),([0-9]+?)\]/', $shapeText, $matches);
        return count($matches) === 3 ? [(string)$matches[1], $matches[2]] : null;
    }

    public static function getBindedCommentToSticker(string $shapeText, string $stickerId): string
    {
        return self::__getBindedCommentWithMiroId($shapeText, $stickerId, self::BINDED_TYPE_STICKER);
    }

    public static function getBindedCommentToConnector(string $shapeText, string $connectorId): string
    {
        return self::__getBindedCommentWithMiroId($shapeText, $connectorId, self::BINDED_TYPE_CONNECTOR);
    }

    private static function __getBindedCommentWithMiroId(string $shapeText, string $miroId, $bindedType): string
    {
        return $shapeText . "\n[" . $bindedType . "," . $miroId . "]";
    }

    public function setSticker(MiroSticker $sticker): void
    {
        $this->sticker = $sticker;
    }

    public function setConnector(MiroConnector $connector): void
    {
        $this->connector = $connector;
    }

    public function getBindedItem(): MiroSticker|MiroConnector|null
    {
        if (!empty($this->sticker)) {
            return $this->sticker;
        }
        if (!empty($this->connector)) {
            return $this->connector;
        }
        return null;
    }

    public function isBindedItemModified(): bool
    {
        // TODO: connectorに繋がれている場合は、矢印/startItem/endItemいずれかが更新されている場合に"更新された”とする
        return $this->getBindedItem()->getModifiedAt() > $this->miroItem->createdAt;
    }
}
