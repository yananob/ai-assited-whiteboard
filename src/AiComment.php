<?php

declare(strict_types=1);

namespace MyApp;

class AiComment
{
    // private int $id;
    private $miroItem;
    private $sticker;
    private $shapeText;

    private function __construct(private $text) {}

    public static function createFromText(string $text): AiComment
    {
        return new AiComment($text);
    }

    public static function createFromShape($miroItem): AiComment
    {
        $aiComment = new AiComment($miroItem->data->content);
        $aiComment->miroItem = $miroItem;
        // $aiComment->id = $miroItem->id;
        $aiComment->shapeText = $miroItem->data->content;
        return $aiComment;
    }

    public function getStickerId(): ?string
    {
        if (empty($this->sticker)) {
            return $this->__extractStickerId($this->getShapeText());
        } else {
            return $this->sticker->id;
        }
    }

    public function isStickerModified(): bool
    {
        if (empty($this->miroItem)) {
            return false;
        }
        return $this->sticker->modifiedAt > $this->miroItem->createdAt;
    }

    private function __extractStickerId(string $shapeText): ?string
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
