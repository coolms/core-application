<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Dashboard;

use CoolMS\Core\Application\Dashboard\DashboardArrangingUnavailableException;
use CoolMS\Core\Application\Dashboard\NoPersonalDashboardLayouts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A host that keeps no per-person settings: every dashboard is the module
 * default, and arranging one is refused out loud rather than kept somewhere it
 * would outlive the request.
 */
#[CoversClass(NoPersonalDashboardLayouts::class)]
final class NoPersonalDashboardLayoutsTest extends TestCase
{
    #[Test]
    public function nothingIsArrangedAndNothingCanBe(): void
    {
        $none = new NoPersonalDashboardLayouts();

        self::assertFalse($none->canArrange());
        self::assertNull($none->entries('main'));
        self::assertSame($none->version(), $none->version());
    }

    #[Test]
    public function aSaveIsRefused(): void
    {
        $this->expectException(DashboardArrangingUnavailableException::class);

        new NoPersonalDashboardLayouts()->save('main', [['widget' => 'a']], null);
    }

    #[Test]
    public function aClearIsRefused(): void
    {
        $this->expectException(DashboardArrangingUnavailableException::class);

        new NoPersonalDashboardLayouts()->clear('main', null);
    }
}
