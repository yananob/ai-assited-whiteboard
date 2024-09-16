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
            if ($connector->getStartItem()->id === $this->getMiroId()) {
                $this->childIds[] = $connector->getEndItem()->id;
            }
            if ($connector->getEndItem()->id === $this->getMiroId()) {
                $this->parentIds[] = $connector->getStartItem()->id;
            }
        }
        // var_dump(str_repeat("-", 80));
        // var_dump($this->getText());
        // var_dump(("parentIds:"));
        // var_dump($this->parentIds);
        // var_dump(("childIds:"));
        // var_dump($this->childIds);
    }

    public function getMiroId(): string
    {
        return $this->miroItem->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getModifiedAt(): string
    {
        return $this->miroItem->modifiedAt;
    }

    public function getPosition(): array
    {
        return ["x" => $this->miroItem->position->x, "y" => $this->miroItem->position->y];
    }

    public function hasParent(): bool
    {
        return !empty($this->parentIds);
    }
}
