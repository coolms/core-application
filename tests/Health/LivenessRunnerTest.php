<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Health;

use CoolMS\Core\Application\Health\LivenessRunner;
use CoolMS\Core\Health\DependencyState;
use CoolMS\Core\Health\LivenessProbeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_column;

/**
 * The number that should be zero, and the ways it could lie.
 *
 * The command exists because a realtime node was dead for nine hours behind green gates,
 * so the failure mode to guard against is not "reports a fault that is not there" but
 * "reports health it has not established".
 */
final class LivenessRunnerTest extends TestCase
{
    #[Test]
    public function theNumberCountsOnlyRequiredDependenciesThatWereAskedAndStayedSilent(): void
    {
        $runner = $this->runner(
            $this->probe(DependencyState::answered('Database', 'SELECT 1', 'ok')),
            $this->probe(DependencyState::silent('Realtime', 'publish', 'no reply')),
            // Optional and down -- a real problem, but not THE number.
            $this->probe(DependencyState::silent('Search index', 'GET /health', 'refused', required: false)),
            // Required but absent -- nothing was asked, so nothing stayed silent.
            $this->probe(DependencyState::notConfigured('SMTP', 'connect', 'no DSN', required: true)),
        );

        self::assertSame(1, $runner->failingRequired($runner->run()));
    }

    #[Test]
    public function aProbeThatThrowsBecomesAFailingRowRatherThanBlindingTheRest(): void
    {
        $runner = $this->runner(
            $this->throwingProbe(),
            $this->probe(DependencyState::answered('Database', 'SELECT 1', 'ok')),
        );

        $states = $runner->run();

        self::assertCount(2, $states, 'the healthy probe is still reported');
        self::assertSame(1, $runner->failingRequired($states));
        self::assertTrue($states[0]->isFailing());
        self::assertStringContainsString(
            'the probe threw',
            $states[0]->detail,
            'and it says the PROBE broke, not the dependency',
        );
    }

    #[Test]
    public function failingDependenciesSortToTheTopWhereTheyWillBeRead(): void
    {
        $states = $this->runner(
            $this->probe(DependencyState::answered('Aaa', 'ask', 'ok')),
            $this->probe(DependencyState::answered('Bbb', 'ask', 'ok')),
            $this->probe(DependencyState::silent('Zzz', 'ask', 'silent')),
        )->run();

        self::assertSame(['Zzz', 'Aaa', 'Bbb'], array_column($states, 'name'));
    }

    #[Test]
    public function anAbsentDependencyIsNeverReportedAsHealthy(): void
    {
        $absent = DependencyState::notConfigured('SMTP', 'connect', 'no DSN');

        self::assertSame('absent', $absent->status(), 'not "ok" -- nothing was asked');
        self::assertFalse($absent->answered);
        self::assertFalse($absent->isFailing());
    }

    #[Test]
    public function configuredIsNotAnAnswer(): void
    {
        // The whole point: a dependency can be configured, wired and compiled, and
        // still be a corpse. The two facts are carried separately so no reader can
        // take one for the other.
        $down = DependencyState::silent('Realtime', 'publish', 'no reply');

        self::assertTrue($down->configured);
        self::assertFalse($down->answered);
        self::assertSame('DOWN', $down->status());
    }

    private function runner(LivenessProbeInterface ...$probes): LivenessRunner
    {
        return new LivenessRunner($probes);
    }

    private function probe(DependencyState $state): LivenessProbeInterface
    {
        return new class($state) implements LivenessProbeInterface {
            public function __construct(private readonly DependencyState $state)
            {
            }

            public function check(): DependencyState
            {
                return $this->state;
            }
        };
    }

    private function throwingProbe(): LivenessProbeInterface
    {
        return new class implements LivenessProbeInterface {
            public function check(): DependencyState
            {
                throw new RuntimeException('probe is broken');
            }
        };
    }
}
