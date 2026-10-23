<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Retention;

use CoolMS\Core\Application\Retention\RetentionPruneRunner;
use CoolMS\Core\Retention\RetentionPopulationInterface;
use CoolMS\Core\Retention\RetentionPrunerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * {@see RetentionPruneRunner} fans the aggregate sweep across every registered
 * {@see RetentionPrunerInterface}: `prune()` reports the per-pruner rows removed
 * (DELETE path) and `preview()` the prunable counts (COUNT path); distinct values
 * prove neither crosses into the other, and each row carries the pruner's key + label.
 */
final class RetentionPruneRunnerTest extends TestCase
{
    #[Test]
    public function pruneReportsEveryPrunersRemovedCount(): void
    {
        $runner = new RetentionPruneRunner([
            $this->pruner('analytics.events', 'Analytics', removed: 7, prunable: 70),
            $this->pruner('comment.spam', 'Spam', removed: 3, prunable: 30),
        ]);

        self::assertSame([
            ['key' => 'analytics.events', 'label' => 'Analytics', 'removed' => 7],
            ['key' => 'comment.spam', 'label' => 'Spam', 'removed' => 3],
        ], $runner->prune());
    }

    #[Test]
    public function previewReportsEveryPrunersPrunableCountWithoutDeleting(): void
    {
        $runner = new RetentionPruneRunner([
            $this->pruner('analytics.events', 'Analytics', removed: 7, prunable: 70),
            $this->pruner('comment.spam', 'Spam', removed: 3, prunable: 30),
        ]);

        self::assertSame([
            ['key' => 'analytics.events', 'label' => 'Analytics', 'prunable' => 70, 'population' => null],
            ['key' => 'comment.spam', 'label' => 'Spam', 'prunable' => 30, 'population' => null],
        ], $runner->preview());
    }

    #[Test]
    public function aPrunerThatCanCountGivesItsPopulationAndOneThatCannotGivesNull(): void
    {
        $counting = new class implements RetentionPrunerInterface, RetentionPopulationInterface {
            public function retentionKey(): string
            {
                return 'search.queries';
            }

            public function retentionLabel(): string
            {
                return 'Searches';
            }

            public function pruneExpired(): int
            {
                return 0;
            }

            public function countExpired(): int
            {
                return 4;
            }

            public function countPopulation(): int
            {
                return 50;
            }
        };
        $runner = new RetentionPruneRunner([
            $counting,
            $this->pruner('comment.spam', 'Spam', removed: 0, prunable: 3),
        ]);

        $preview = $runner->preview();

        self::assertSame(50, $preview[0]['population']);
        self::assertNull($preview[1]['population'], 'unknown, not zero');
    }

    #[Test]
    public function noRegisteredPrunersYieldsEmptyResults(): void
    {
        $runner = new RetentionPruneRunner([]);

        self::assertSame([], $runner->prune());
        self::assertSame([], $runner->preview());
    }

    private function pruner(string $key, string $label, int $removed, int $prunable): RetentionPrunerInterface
    {
        return new class($key, $label, $removed, $prunable) implements RetentionPrunerInterface {
            public function __construct(
                private readonly string $key,
                private readonly string $label,
                private readonly int $removed,
                private readonly int $prunable,
            ) {
            }

            public function retentionKey(): string
            {
                return $this->key;
            }

            public function retentionLabel(): string
            {
                return $this->label;
            }

            public function pruneExpired(): int
            {
                return $this->removed;
            }

            public function countExpired(): int
            {
                return $this->prunable;
            }
        };
    }
}
