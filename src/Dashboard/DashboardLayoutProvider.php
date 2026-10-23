<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

use CoolMS\Core\Application\Config\ConfigLoaderInterface;
use CoolMS\Core\Dashboard\DashboardLayout;
use CoolMS\Core\Dashboard\DashboardPlacement;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * The arrangement a dashboard is drawn with: the person's own, else the module default.
 *
 * ## Two sources, and only one of them is ever written
 *
 * - The person's own arrangement, from {@see PersonalDashboardLayoutsInterface}:
 *   what they saved, kept by the host with their other settings.
 * - The module DEFAULT, from config DATA -- `config/modules/core/dashboard/dashboard.yaml`
 *   for the main dashboard -- found the way every datagrid, navigraph tree and
 *   editor profile is found: by `type` and `id` through
 *   {@see ConfigLoaderInterface}. The application never writes it; it changes
 *   with the code, in a commit.
 *
 * A person who arranged a dashboard sees their arrangement and nothing of the
 * default's; one who never did sees the default. There is no merge between the
 * two: a merge would make "my arrangement" depend on edits to a file the
 * person cannot see.
 *
 * ## Everything here is UNTRUSTED, and that is deliberate
 *
 * A human edits the YAML, and the stored arrangement is whatever a client
 * once sent. So a bad entry is dropped and logged rather than thrown, and the
 * dashboard renders without it -- the opposite of {@see DashboardPlacement},
 * which refuses to exist in a wrong state because a bad width THERE is a
 * programmer's mistake in a module's own code.
 *
 * The two rules are not in tension, they are the layers doing their own jobs:
 * the value object guarantees it is never wrong, and this decides what to do
 * about input that is. A typo should cost one card, not the page.
 */
final readonly class DashboardLayoutProvider
{
    /** The `type:` every dashboard layout file declares. */
    public const string CONFIG_TYPE = 'dashboard';

    /**
     * The main `/admin/dashboard`.
     *
     * An id rather than a bare filename because section dashboards are the next
     * thing this feeds -- {@see \CoolMS\Core\Dashboard\DashboardWidget::$group}
     * exists for them -- and they will each want their own arrangement under the
     * same `type`.
     */
    public const string MAIN = 'main';

    public function __construct(
        private ConfigLoaderInterface $config,
        private LoggerInterface $logger,
        private PersonalDashboardLayoutsInterface $personal,
    ) {
    }

    /**
     * The arrangement this viewer sees for one dashboard, or an empty layout.
     *
     * An empty layout is the normal case, not a failure: with nothing saved and
     * no default declared, the catalogue's own order stands.
     */
    public function load(string $dashboard = self::MAIN): DashboardLayout
    {
        return $this->personal($dashboard) ?? $this->moduleDefault($dashboard);
    }

    /** The viewer's own arrangement of one dashboard, or null when they never arranged it. */
    public function personal(string $dashboard = self::MAIN): ?DashboardLayout
    {
        $entries = $this->personal->entries($dashboard);

        return null === $entries ? null : $this->layout($entries, $dashboard, 'personal');
    }

    /** What the modules declare for one dashboard, read from config and never written. */
    public function moduleDefault(string $dashboard = self::MAIN): DashboardLayout
    {
        // !! No theme slug. The loader merges a theme override with
        // array_merge_recursive, which CONCATENATES lists -- it would append a
        // second copy of every placement rather than replacing them. A layout
        // is a list, so it is not overridable that way, and asking for it would
        // silently duplicate cards.
        $raw = $this->config->load(self::CONFIG_TYPE, $dashboard);

        if (null === $raw || !is_array($raw['widgets'] ?? null)) {
            return DashboardLayout::none();
        }

        return $this->layout($raw['widgets'], $dashboard, 'module default');
    }

    /** @param array<mixed> $entries */
    private function layout(array $entries, string $dashboard, string $source): DashboardLayout
    {
        $placements = [];
        foreach ($entries as $index => $entry) {
            $placement = $this->placement($entry, $dashboard . ' (' . $source . ')', (string) $index);
            if (null !== $placement) {
                $placements[] = $placement;
            }
        }

        return new DashboardLayout($placements);
    }

    private function placement(mixed $entry, string $dashboard, string $index): ?DashboardPlacement
    {
        if (!is_array($entry) || !is_string($entry['widget'] ?? null) || '' === $entry['widget']) {
            $this->warn($dashboard, $index, 'entry has no "widget" id');

            return null;
        }

        $columns = $entry['columns'] ?? null;
        if (null !== $columns && !is_int($columns)) {
            $this->warn($dashboard, $index, '"columns" must be a whole number');

            return null;
        }

        $hidden = $entry['hidden'] ?? false;
        if (!is_bool($hidden)) {
            $this->warn($dashboard, $index, '"hidden" must be true or false');

            return null;
        }

        try {
            return new DashboardPlacement($entry['widget'], $columns, $hidden);
        } catch (InvalidArgumentException $e) {
            // A width outside the grid. The value object's job is to refuse it;
            // this one's is to keep the other cards on the screen.
            $this->warn($dashboard, $index, $e->getMessage());

            return null;
        }
    }

    private function warn(string $dashboard, string $index, string $why): void
    {
        $this->logger->warning('Dashboard layout "{dashboard}": ignoring entry #{index} — {why}', [
            'dashboard' => $dashboard,
            'index' => $index,
            'why' => $why,
        ]);
    }
}
