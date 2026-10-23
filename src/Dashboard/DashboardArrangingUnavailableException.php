<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

use RuntimeException;

/** This installation keeps no arrangement per person, so a dashboard cannot be arranged: the module default stands. */
final class DashboardArrangingUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Dashboards cannot be arranged on this installation: it keeps no personal settings.');
    }
}
