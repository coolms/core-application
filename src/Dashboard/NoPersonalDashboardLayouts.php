<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

/**
 * The answer when the host keeps no per-person settings: every dashboard is the
 * module default, and arranging one is refused out loud rather than written
 * somewhere it would outlive the request.
 */
final readonly class NoPersonalDashboardLayouts implements PersonalDashboardLayoutsInterface
{
    public function canArrange(): bool
    {
        return false;
    }

    public function version(): string
    {
        // Nothing is ever stored, so nothing ever changes.
        return 'none';
    }

    public function entries(string $dashboard): ?array
    {
        return null;
    }

    public function save(string $dashboard, array $entries, ?string $expectedVersion): string
    {
        throw new DashboardArrangingUnavailableException();
    }

    public function clear(string $dashboard, ?string $expectedVersion): string
    {
        throw new DashboardArrangingUnavailableException();
    }
}
