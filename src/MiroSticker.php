<?php

declare(strict_types=1);

namespace MyApp;

/**
 * Miro上の付箋オブジェクト
 */
class MiroSticker
{
    private string $text;
    private array $parentIds;
    private array $childIds;

    public function __construct(private $miroItem, array $connectors)
    {
        $this->text = $miroItem->data->content;

        // parentId/childIdのセット
        $this->parentIds = [];
        $this->childIds = [];
        foreach ($connectors as $connector) {
            if ($connector->getStartItemId() === $this->getMiroId()) {
                $this->childIds[] = $connector->getEndItemId();
            }
            if ($connector->getEndItemId() === $this->getMiroId()) {
                $this->parentIds[] = $connector->getStartItemId();
            }
        }
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

    public function getPosition(): array
    {
        return ["x" => $this->miroItem->position->x, "y" => $this->miroItem->position->y];
    }

    public function hasParentStickers(): bool
    {
        return !empty($this->parentIds);
    }

    public function hasChildStickers(): bool
    {
        return !empty($this->childIds);
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
