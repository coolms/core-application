<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Health;

use CoolMS\Core\Health\DependencyState;
use CoolMS\Core\Health\LivenessProbeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

use function array_filter;
use function count;
use function sprintf;
use function usort;

/**
 * Asks every registered {@see LivenessProbeInterface} and collects the answers --
 * the single seam behind `coolms:doctor`.
 *
 * **One broken probe must never blind an operator to the rest.** A probe that throws
 * is caught here and becomes a failing row naming the exception, so the report stays
 * complete and the fault is attributed to the PROBE rather than silently to the
 * dependency. Letting it escape would mean a typo in one module hiding the seven
 * dependencies someone came to check.
 *
 * The `#[AutowireIterator]` pin is deliberate, per the tagged-iterator footgun that
 * {@see \CoolMS\Core\Application\Retention\RetentionPruneRunner} documents: binding
 * the collection through the Core Extension's `setArgument` would be clobbered by the
 * `App\:` services glob re-registering this class, so the tag is consumed on the
 * constructor parameter directly.
 */
final readonly class LivenessRunner
{
    /**
     * @param iterable<LivenessProbeInterface> $probes
     */
    public function __construct(
        #[AutowireIterator('coolms.diagnostics.probe')]
        private iterable $probes = [],
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Every dependency's state, failing ones first and then by name -- an operator
     * reads the top of this list, so what is wrong belongs there.
     *
     * @return list<DependencyState>
     */
    public function run(): array
    {
        $states = [];
        foreach ($this->probes as $probe) {
            $states[] = $this->ask($probe);
        }

        $rank = static fn (DependencyState $s): array => [$s->isFailing() ? 0 : 1, $s->name];
        usort($states, static fn (DependencyState $a, DependencyState $b): int => $rank($a) <=> $rank($b));

        return $states;
    }

    /**
     * The number that should be zero: required dependencies that are configured and
     * did not answer. An absent optional service is NOT counted -- it is a different
     * problem, and counting it here would bury the one that matters.
     *
     * @param list<DependencyState> $states
     */
    public function failingRequired(array $states): int
    {
        return count(array_filter($states, static fn (DependencyState $s): bool => $s->isFailing()));
    }

    private function ask(LivenessProbeInterface $probe): DependencyState
    {
        try {
            return $probe->check();
        } catch (Throwable $e) {
            $name = $probe::class;
            $this->logger->warning('A liveness probe threw; reporting it as failing', [
                'probe' => $name,
                'reason' => $e->getMessage(),
            ]);

            return DependencyState::silent(
                $name,
                'the probe itself',
                sprintf('the probe threw %s: %s', $e::class, $e->getMessage()),
            );
        }
    }
}
