<?php

declare(strict_types=1);

namespace MyApp;

/**
 * Miro上のコメントオブジェクト
 */
class MiroComment
{
    private $sticker;
    private string $text;
    private string $shapeText;

    public function __construct(private $miroItem)
    {
        $this->text = $miroItem->data->content;
    }

    public function getStickerId(): string
    {
        return $this->sticker->id;
    }

    public function isStickerModified(): bool
    {
        return $this->sticker->modifiedAt > $this->miroItem->createdAt;
    }

    public static function extractStickerId(string $shapeText): ?string
    {
        preg_match('/^\[([0-9]+)\]/', $shapeText, $matches);
        return count($matches) === 2 ? $matches[1] : null;
    }

    public function setSticker($sticker): void
    {
        $this->sticker = $sticker;
        $this->shapeText = "[" . $this->sticker->id . "]" . $this->text;
    }

    public function getMiroId(): string
    {
        return $this->miroItem->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getShapeText(): string
    {
        return $this->shapeText;
    }
}
