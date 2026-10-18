<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

use RuntimeException;

/**
 * The person's arrangements changed since the version a save named: nothing was written.
 *
 * The version covers all of a person's dashboards, so this says "your dashboards
 * were changed elsewhere", not "this dashboard was".
 */
final class StaleDashboardLayoutsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Your dashboards were changed elsewhere since this arrangement was read.');
    }
}
