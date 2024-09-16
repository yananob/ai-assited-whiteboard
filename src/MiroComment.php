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

    public static function extractStickerId(string $shapeText): ?string
    {
        preg_match('/\[([0-9]+?)\]/', $shapeText, $matches);
        return count($matches) === 2 ? $matches[1] : null;
    }

    public static function bindMiroId(string $shapeText, string $stickerId): string
    {
        return $shapeText . "\n[" . $stickerId . "]";
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
        return $this->getBindedItem()->getModifiedAt() > $this->miroItem->createdAt;
    }
}
