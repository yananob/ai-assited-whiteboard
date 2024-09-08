<?php

declare(strict_types=1);

namespace MyApp;

/**
 * Miro上のコメントオブジェクト
 */
class MiroComment
{
    // private int $id;
    // private $miroItem;
    private $sticker;
    private string $text;
    private string $shapeText;

    public function __construct(private $miroItem)
    {
        // $miroComment = new MiroComment($miroItem->data->content);
        // $miroComment->miroItem = $miroItem;
        // $miroComment->id = $miroItem->id;
        $this->text = $miroItem->data->content;
    }

    // public static function createFromText(string $text): MiroComment
    // {
    //     return new MiroComment($text);
    // }

    // public static function createFromShape($miroItem): MiroComment
    // {
    //     $miroComment = new MiroComment($miroItem->data->content);
    //     $miroComment->miroItem = $miroItem;
    //     // $miroComment->id = $miroItem->id;
    //     $miroComment->shapeText = $miroItem->data->content;
    //     return $miroComment;
    // }

    public function getStickerId(): string
    {
        // if (empty($this->sticker)) {
        //     return $this->__extractStickerId($this->getShapeText());
        // } else {
        return $this->sticker->id;
        // }
    }

    public function isStickerModified(): bool
    {
        // if (empty($this->miroItem)) {
        //     return false;
        // }
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
