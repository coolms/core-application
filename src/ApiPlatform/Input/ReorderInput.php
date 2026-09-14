<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\ApiPlatform\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generic reorder input DTO.
 * Body: { "items": [{"id": "<uuid>", "sortOrder": <int>}, ...] }.
 *
 * Consumed by each module's own reorder processor (the Field module ships the first).
 */
final readonly class ReorderInput
{
    /** @var ReorderInputItem[] */
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 500)]
    #[Assert\Valid]
    public array $items;

    /**
     * Normalises each element to a ReorderInputItem regardless of whether the
     * Symfony Serializer passed a plain array (JSON object) or an object.
     *
     * @param array<int, array<string, mixed>|ReorderInputItem> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_map(
            static function (mixed $item): ReorderInputItem {
                if ($item instanceof ReorderInputItem) {
                    return $item;
                }

                /* @var array<string, mixed> $item */
                return new ReorderInputItem(
                    id: (string) ($item['id'] ?? ''),
                    sortOrder: (int) ($item['sortOrder'] ?? 0),
                );
            },
            $items,
        );
    }
}
