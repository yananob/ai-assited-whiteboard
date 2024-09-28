<?php

declare(strict_types=1);

namespace MyApp;

/**
 * Miro上のコネクターオブジェクト
 */
class MiroConnector
{
    private string $text;

    public function __construct(private $miroItem)
    {
        $this->text = $miroItem->captions[0]->content;
    }

    public function getMiroId(): string
    {
        return $this->miroItem->id;
    }

    public function getTextWithTags(): string
    {
        return $this->text;
    }

    public function getText(): string
    {
        return strip_tags($this->getTextWithTags());
    }

    public function getModifiedAt(): string
    {
        return $this->miroItem->modifiedAt;
    }

    public function getStartItemId(): string
    {
        return $this->miroItem->startItem->id;
    }

    public function getEndItemId(): string
    {
        return $this->miroItem->endItem->id;
    }

    public function hasAiComment(array $aiComments): bool
    {
        foreach ($aiComments as $miroComment) {
            if ($this->getMiroId() === $miroComment->getBindedItem()->getMiroId()) {
                return true;
            }
        }
        return false;
    }
}
