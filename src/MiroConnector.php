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
        $this->text = property_exists($miroItem, 'captions') ? $miroItem->captions[0]->content : '';
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

    public function getStartItemId(): ?string
    {
        return property_exists($this->miroItem, 'startItem') ? $this->miroItem->startItem->id : null;
    }

    public function getEndItemId(): ?string
    {
        return property_exists($this->miroItem, 'endItem') ? $this->miroItem->endItem->id : null;
    }

    public function hasAiComment(array $aiComments): bool
    {
        return is_null($this->getBindedComment($aiComments)) ? false : true;
    }

    public function getBindedComment(array $aiComments): ?MiroComment
    {
        foreach ($aiComments as $miroComment) {
            if ($this->getMiroId() === $miroComment->getBindedItem()->getMiroId()) {
                return $miroComment;
            }
        }
        return null;
    }
}
