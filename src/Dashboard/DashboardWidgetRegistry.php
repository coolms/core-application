<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Dashboard;

use CoolMS\Core\Dashboard\DashboardWidget;
use CoolMS\Core\Dashboard\DashboardWidgetProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function in_array;

/**
 * Every {@see DashboardWidgetProviderInterface} collapsed into one catalogue,
 * filtered to what the current viewer may be offered.
 *
 * Cached for one request -- the providers are cheap and the answer cannot
 * change within a request -- and forgotten between requests ({@see reset()}).
 *
 * !! The answer is per VIEWER: it is filtered by the viewer's roles. A cache
 * kept for the registry's lifetime is right only where every request is a new
 * process; in a long-running worker, or a test client that keeps its kernel,
 * the first viewer's list would be handed to the next. Each widget's own
 * endpoint still refuses data the viewer may not read, so the leak would be the
 * catalogue, never the data -- but the catalogue is what this class promises.
 */
final class DashboardWidgetRegistry implements ResetInterface
{
    /** @var list<DashboardWidget>|null */
    private ?array $widgets = null;

    /**
     * !! The iterator is PINNED on the parameter rather than left to the
     * extension's `setArguments()`. `App\` is globbed with autowiring and the
     * glob runs after the extension, so an explicit tagged-iterator argument is
     * silently replaced by "autowire an `iterable`" -- which cannot be resolved
     * and fails the container build. Same trap as the file-kind registry.
     *
     * @param iterable<DashboardWidgetProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('coolms.core.dashboard_widget_provider')]
        private readonly iterable $providers,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    /**
     * The widgets this viewer may be offered, in provider order.
     *
     * Two things are refused here rather than passed on:
     *
     *  - **A duplicate id.** Layouts are stored against the id, so two widgets
     *    sharing one would make a saved dashboard ambiguous. First wins, which
     *    keeps the answer stable when a module is added.
     *  - **An unknown `kind`.** The client can only draw what it knows; a card
     *    it cannot draw is worse than one that is absent, and rendering it as
     *    something else would show a number that means another thing.
     *
     * !! The role filter is a DISPLAY filter and nothing is trusted to it. Each
     * widget's own endpoint is the authority on whether its data may be read --
     * see {@see DashboardWidget} for why both exist.
     *
     * @return list<DashboardWidget>
     */
    public function forCurrentUser(): array
    {
        if (null !== $this->widgets) {
            return $this->widgets;
        }

        $out = [];
        $seen = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->dashboardWidgets() as $widget) {
                if (array_key_exists($widget->id, $seen)) {
                    continue;
                }
                if (!in_array($widget->kind, DashboardWidget::KINDS, true)) {
                    continue;
                }
                if (null !== $widget->requiredRole && !$this->authorization->isGranted($widget->requiredRole)) {
                    continue;
                }

                $seen[$widget->id] = true;
                $out[] = $widget;
            }
        }

        return $this->widgets = $out;
    }

    /** Forget the last viewer's catalogue: the kernel calls it between requests (`kernel.reset`). */
    public function reset(): void
    {
        $this->widgets = null;
    }
}
