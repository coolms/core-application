<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\ApiPlatform\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One item of {@see ReorderInput}: the entity and the position it takes.
 *
 * Its own file: declared beside ReorderInput it could only be autoloaded
 * after that file had been -- measured 2026-09-14, a cold class_exists() was
 * false -- and PSR-4 has no way to find a second class in a file.
 */
final readonly class ReorderInputItem
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $id = '',
        #[Assert\GreaterThanOrEqual(0)]
        public int $sortOrder = 0,
    ) {
    }
}
