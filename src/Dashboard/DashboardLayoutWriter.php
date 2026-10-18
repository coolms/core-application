<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

use CoolMS\Core\Dashboard\DashboardPlacement;
use InvalidArgumentException;

use function array_key_exists;
use function implode;
use function preg_match;
use function sprintf;

/**
 * Saves the signed-in person's arrangement of a dashboard.
 *
 * ## Into the person's settings, never into config
 *
 * An arrangement is one person's runtime state. It goes to
 * {@see PersonalDashboardLayoutsInterface}, under that person's version, and
 * nowhere else: the module's YAML is the default and is never written by the
 * application (it used to be -- every admin's drag became a diff in the served
 * tree, and rearranged everyone else's dashboard with it).
 *
 * ## What a save may change, and what it cannot
 *
 * Order, width, hidden. Nothing else reaches storage: a placement carries a
 * widget ID and two numbers, so there is no shape in which a saved layout could
 * alter an `endpoint` or a `requiredRole`. That is deliberate -- an arrangement
 * able to rewrite either would quietly overrule a module's security, and the
 * layout is applied AFTER the permission filter precisely so it cannot.
 *
 * ## Saving is PARTIAL, because the catalogue is
 *
 * The registry hides widgets whose `requiredRole` the viewer lacks today. A
 * placement the person made while they held that role is KEPT, after the
 * submitted ones and in its existing relative order, so the card comes back
 * where they put it when the role does.
 */
final readonly class DashboardLayoutWriter
{
    public function __construct(
        private DashboardWidgetRegistry $widgets,
        private DashboardLayoutProvider $layouts,
        private PersonalDashboardLayoutsInterface $personal,
    ) {
    }

    /**
     * @param list<DashboardPlacement> $placements      in the order they should appear
     * @param string|null              $expectedVersion the version the arrangement was made from (If-Match)
     *
     * @throws InvalidArgumentException                 when a placement names a widget this viewer is not
     *                                                  offered, or the dashboard id is not a plain token
     * @throws StaleDashboardLayoutsException          when the person's arrangements changed since that version
     * @throws DashboardArrangingUnavailableException when the installation keeps no personal arrangements
     *
     * @return string the version written
     */
    public function save(array $placements, ?string $section = null, ?string $expectedVersion = null): string
    {
        $dashboard = $this->dashboard($section);

        // Before the widget checks: on an installation that cannot keep an
        // arrangement, "you cannot arrange here" is the true answer whatever
        // the body says.
        if (!$this->personal->canArrange()) {
            throw new DashboardArrangingUnavailableException();
        }

        // Only the widgets THIS dashboard shows. Saving the Content section
        // must not be able to place a card that belongs to the main dashboard --
        // a widget lives on exactly one of them, and a save is a statement
        // about the one being arranged.
        $offered = [];
        foreach ($this->widgets->forCurrentUser() as $widget) {
            if (($widget->group ?? null) === $section) {
                $offered[$widget->id] = true;
            }
        }

        // Rejected rather than silently dropped at render time. This is the one
        // moment someone is present to be told: a typo in an id saved quietly
        // becomes a card that never appears, with nothing to read but the log.
        $unknown = [];
        foreach ($placements as $placement) {
            if (!array_key_exists($placement->widget, $offered)) {
                $unknown[] = $placement->widget;
            }
        }
        if ([] !== $unknown) {
            throw new InvalidArgumentException(sprintf('No installed module offers %s.', implode(', ', $unknown)));
        }

        $submitted = [];
        foreach ($placements as $placement) {
            $submitted[$placement->widget] = true;
        }

        $kept = [];
        foreach ($this->layouts->personal($dashboard)?->placements ?? [] as $existing) {
            // Only what this viewer could not have been shown, and did not
            // resubmit -- anything they CAN see, they have just spoken for.
            if (!array_key_exists($existing->widget, $offered) && !array_key_exists($existing->widget, $submitted)) {
                $kept[] = $existing;
            }
        }

        return $this->personal->save($dashboard, $this->toArray([...$placements, ...$kept]), $expectedVersion);
    }

    /**
     * Drop the person's arrangement so the module default applies again.
     *
     * @throws InvalidArgumentException                 when the dashboard id is not a plain token
     * @throws StaleDashboardLayoutsException          when the person's arrangements changed since that version
     * @throws DashboardArrangingUnavailableException when the installation keeps no personal arrangements
     *
     * @return string the version written
     */
    public function reset(?string $section = null, ?string $expectedVersion = null): string
    {
        return $this->personal->clear($this->dashboard($section), $expectedVersion);
    }

    private function dashboard(?string $section): string
    {
        $dashboard = $section ?? DashboardLayoutProvider::MAIN;
        if (1 !== preg_match(PersonalDashboardLayoutsInterface::DASHBOARD_ID_PATTERN, $dashboard)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a dashboard.', $dashboard));
        }

        return $dashboard;
    }

    /**
     * @param list<DashboardPlacement> $placements
     *
     * @return list<array<string, mixed>>
     */
    private function toArray(array $placements): array
    {
        $out = [];
        foreach ($placements as $placement) {
            // Only what was actually decided. A null `columns` means "keep the
            // module's width" -- writing it out as a number would freeze the
            // card at whatever it happened to be, which is the difference
            // between an arrangement and a snapshot.
            $entry = ['widget' => $placement->widget];
            if (null !== $placement->columns) {
                $entry['columns'] = $placement->columns;
            }
            if ($placement->hidden) {
                $entry['hidden'] = true;
            }

            $out[] = $entry;
        }

        return $out;
    }
}
