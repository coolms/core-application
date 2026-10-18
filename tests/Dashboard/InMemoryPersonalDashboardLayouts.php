<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Dashboard;

use CoolMS\Core\Application\Dashboard\DashboardArrangingUnavailableException;
use CoolMS\Core\Application\Dashboard\PersonalDashboardLayoutsInterface;
use CoolMS\Core\Application\Dashboard\StaleDashboardLayoutsException;

/**
 * One person's arrangements, held in memory the way a host holds them: one
 * version for all of them, compared before anything is written.
 */
final class InMemoryPersonalDashboardLayouts implements PersonalDashboardLayoutsInterface
{
    /**
     * Every save and clear it was asked for, in order: [action, dashboard, entries, expected version].
     *
     * @var list<array{string, string, list<array<string, mixed>>|null, string|null}>
     */
    public array $writes = [];

    private int $version = 1;

    /** @param array<string, list<mixed>> $layouts dashboard id to its stored entries */
    public function __construct(
        public array $layouts = [],
        private readonly bool $arrangeable = true,
    ) {
    }

    public function canArrange(): bool
    {
        return $this->arrangeable;
    }

    public function version(): string
    {
        return 'v' . $this->version;
    }

    public function entries(string $dashboard): ?array
    {
        return $this->layouts[$dashboard] ?? null;
    }

    public function save(string $dashboard, array $entries, ?string $expectedVersion): string
    {
        $this->check($expectedVersion);
        $this->writes[] = ['save', $dashboard, $entries, $expectedVersion];
        $this->layouts[$dashboard] = $entries;
        ++$this->version;

        return $this->version();
    }

    public function clear(string $dashboard, ?string $expectedVersion): string
    {
        $this->check($expectedVersion);
        $this->writes[] = ['clear', $dashboard, null, $expectedVersion];
        unset($this->layouts[$dashboard]);
        ++$this->version;

        return $this->version();
    }

    private function check(?string $expectedVersion): void
    {
        if (!$this->arrangeable) {
            throw new DashboardArrangingUnavailableException();
        }
        if (null !== $expectedVersion && $expectedVersion !== $this->version()) {
            throw new StaleDashboardLayoutsException();
        }
    }
}
