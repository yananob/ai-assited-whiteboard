<?php

declare(strict_types=1);

namespace MyApp;

/**
 * Miro上のコメントオブジェクト
 */
class MiroComment
{
    private $sticker;
    // private string $text;
    private string $shapeText;

    public function __construct(private $miroItem)
    {
        $this->shapeText = $miroItem->data->content;
    }

    public static function extractStickerId(string $shapeText): ?string
    {
        var_dump(($shapeText));
        preg_match('/\[([0-9]+?)\]/', $shapeText, $matches);
        return count($matches) === 2 ? $matches[1] : null;
    }

    public static function bindStickerId(string $shapeText, string $stickerId): string
    {
        return $shapeText . "\n[" . $stickerId . "]";
    }

    public function setSticker($sticker): void
    {
        $this->sticker = $sticker;
    }

    public function getStickerId(): string
    {
        return $this->sticker->id;
    }

    public function isStickerModified(): bool
    {
        return $this->sticker->modifiedAt > $this->miroItem->createdAt;
    }

    public function getMiroId(): string
    {
        return $this->miroItem->id;
    }

    public function getText(): string
    {
        return $this->shapeText;
    }
}
