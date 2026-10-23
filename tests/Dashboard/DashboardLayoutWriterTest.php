<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Dashboard;

use CoolMS\Core\Application\Config\ConfigLoaderInterface;
use CoolMS\Core\Application\Dashboard\DashboardArrangingUnavailableException;
use CoolMS\Core\Application\Dashboard\DashboardLayoutProvider;
use CoolMS\Core\Application\Dashboard\DashboardLayoutWriter;
use CoolMS\Core\Application\Dashboard\DashboardWidgetRegistry;
use CoolMS\Core\Application\Dashboard\StaleDashboardLayoutsException;
use CoolMS\Core\Dashboard\DashboardPlacement;
use CoolMS\Core\Dashboard\DashboardWidget;
use CoolMS\Core\Dashboard\DashboardWidgetProviderInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function array_map;

/**
 * Saving one person's arrangement.
 *
 * The tests that matter are about what a save must NOT do: write anywhere but
 * the person's own arrangements, invent widths nobody chose, overwrite a newer
 * arrangement, and drop a placement its author can no longer see.
 */
#[CoversClass(DashboardLayoutWriter::class)]
final class DashboardLayoutWriterTest extends TestCase
{
    #[Test]
    public function theSubmittedOrderIsWhatGetsStoredAsThePersonsOwn(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts();
        $writer = $this->writer($personal, offered: ['a', 'b']);

        $version = $writer->save([new DashboardPlacement('b', columns: 6), new DashboardPlacement('a')]);

        self::assertSame(
            [['save', 'main', [['widget' => 'b', 'columns' => 6], ['widget' => 'a']], null]],
            $personal->writes,
        );
        self::assertSame('v2', $version);
    }

    /**
     * !! The module default is never what a save changes. The writer reads the
     * module's YAML through a loader it can only READ -- there is no writer of
     * config in its reach -- and asks it nothing while saving.
     */
    #[Test]
    public function aSaveNeverReadsOrWritesTheModuleDefault(): void
    {
        $loader = new CountingConfigLoader(['widgets' => [['widget' => 'a', 'columns' => 3]]]);
        $personal = new InMemoryPersonalDashboardLayouts();

        $this->writer($personal, offered: ['a'], loader: $loader)->save([new DashboardPlacement('a')]);

        self::assertSame(0, $loader->loads);
        self::assertSame([['widget' => 'a']], $personal->layouts['main']);
    }

    #[Test]
    public function theVersionTheArrangementWasMadeFromIsPassedOn(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts();

        $this->writer($personal, offered: ['a'])->save([new DashboardPlacement('a')], null, 'v1');

        self::assertSame('v1', $personal->writes[0][3]);
    }

    #[Test]
    public function aSaveFromAnOlderVersionIsRefusedAndWritesNothing(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts(['main' => [['widget' => 'a', 'columns' => 3]]]);
        $writer = $this->writer($personal, offered: ['a']);
        $writer->save([new DashboardPlacement('a', columns: 4)], null, 'v1');

        try {
            $writer->save([new DashboardPlacement('a', columns: 8)], null, 'v1');
            self::fail('A save naming v1 after v2 was written must be refused.');
        } catch (StaleDashboardLayoutsException) {
        }

        self::assertSame([['widget' => 'a', 'columns' => 4]], $personal->layouts['main']);
    }

    /**
     * !! A null width must NOT be written out as a number. "Keep the module's
     * width" and "make it four" are different instructions, and storing the
     * second when the first was meant freezes the card forever -- a module that
     * later improves its own default is then overruled by a number nobody chose.
     */
    #[Test]
    public function aPlacementWithNoWidthStoresNoWidth(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts();

        $this->writer($personal, offered: ['a'])->save([new DashboardPlacement('a')]);

        self::assertSame([['widget' => 'a']], $personal->layouts['main']);
    }

    #[Test]
    public function aHiddenPlacementIsStoredAsHidden(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts();

        $this->writer($personal, offered: ['a'])->save([new DashboardPlacement('a', hidden: true)]);

        self::assertSame([['widget' => 'a', 'hidden' => true]], $personal->layouts['main']);
    }

    /**
     * !! A placement the person made while they could see the widget survives a
     * save made after they lost it -- so the card returns where they put it
     * when the role comes back.
     */
    #[Test]
    public function placementsForWidgetsThisPersonCannotSeeNowSurviveTheSave(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts(['main' => [
            ['widget' => 'restricted', 'columns' => 12],
            ['widget' => 'visible', 'columns' => 3],
        ]]);

        $this->writer($personal, offered: ['visible'])->save([new DashboardPlacement('visible', columns: 6)]);

        self::assertSame(
            [['widget' => 'visible', 'columns' => 6], ['widget' => 'restricted', 'columns' => 12]],
            $personal->layouts['main'],
        );
    }

    /** Arranging one section leaves the others' arrangements as they were. */
    #[Test]
    public function aSectionIsSavedUnderItsOwnIdAndTheOthersAreUntouched(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts(['main' => [['widget' => 'a']]]);

        $this->writer($personal, offered: ['a'], group: 'content')
            ->save([new DashboardPlacement('a', columns: 4)], 'content');

        self::assertSame([['widget' => 'a']], $personal->layouts['main']);
        self::assertSame([['widget' => 'a', 'columns' => 4]], $personal->layouts['content']);
    }

    /**
     * The one moment someone is present to be told. A typo saved quietly becomes
     * a card that never appears, with nothing to read but a log line.
     */
    #[Test]
    public function aWidgetNoModuleOffersIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/typo\.widget/');

        $this->writer(new InMemoryPersonalDashboardLayouts(), offered: ['a'])
            ->save([new DashboardPlacement('a'), new DashboardPlacement('typo.widget')]);
    }

    /**
     * A widget hidden from THIS viewer cannot be placed by them either -- the
     * catalogue is the only thing a save is allowed to talk about, which is
     * what stops a hand-crafted PUT from re-adding a card the registry refused.
     */
    #[Test]
    public function aWidgetTheViewerCannotSeeCannotBePlaced(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->writer(new InMemoryPersonalDashboardLayouts(), offered: ['visible'])
            ->save([new DashboardPlacement('restricted')]);
    }

    /** The id becomes a key in the person's settings: a plain token, never whatever the query carried. */
    #[Test]
    public function aDashboardIdThatIsNotAPlainTokenIsRefused(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts();

        try {
            $this->writer($personal, offered: [])->save([], '../main');
            self::fail('"../main" must not become a dashboard id.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('is not a dashboard', $e->getMessage());
        }

        self::assertSame([], $personal->writes);
    }

    /** "You cannot arrange here" is the answer on such an installation, whatever the body says. */
    #[Test]
    public function anInstallationThatKeepsNoArrangementsRefusesBeforeLookingAtTheBody(): void
    {
        $this->expectException(DashboardArrangingUnavailableException::class);

        $this->writer(new InMemoryPersonalDashboardLayouts(arrangeable: false), offered: ['a'])
            ->save([new DashboardPlacement('typo.widget')]);
    }

    #[Test]
    public function resetDropsThePersonsArrangementAgainstTheirVersion(): void
    {
        $personal = new InMemoryPersonalDashboardLayouts(['main' => [['widget' => 'a']]]);

        $version = $this->writer($personal, offered: [])->reset(null, 'v1');

        self::assertSame([['clear', 'main', null, 'v1']], $personal->writes);
        self::assertArrayNotHasKey('main', $personal->layouts);
        self::assertSame('v2', $version);
    }

    /** @param list<string> $offered */
    private function writer(
        InMemoryPersonalDashboardLayouts $personal,
        array $offered,
        ?string $group = null,
        ?CountingConfigLoader $loader = null,
    ): DashboardLayoutWriter {
        return new DashboardLayoutWriter(
            $this->registry($offered, $group),
            new DashboardLayoutProvider($loader ?? new CountingConfigLoader(null), new NullLogger(), $personal),
            $personal,
        );
    }

    /** @param list<string> $ids */
    private function registry(array $ids, ?string $group): DashboardWidgetRegistry
    {
        $widgets = array_map(
            static fn (string $id): DashboardWidget => new DashboardWidget(
                id: $id,
                label: $id,
                icon: 'bi-graph-up',
                endpoint: '/api/v1/' . $id,
                valuePath: 'count',
                group: $group,
            ),
            $ids,
        );

        $provider = new class($widgets) implements DashboardWidgetProviderInterface {
            /** @param list<DashboardWidget> $widgets */
            public function __construct(private readonly array $widgets)
            {
            }

            public function dashboardWidgets(): array
            {
                return $this->widgets;
            }
        };

        return new DashboardWidgetRegistry([$provider], new class implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return true;
            }
        });
    }
}

/** Answers one fixed module default, and counts how often it was asked. */
final class CountingConfigLoader implements ConfigLoaderInterface
{
    public int $loads = 0;

    /** @param array<string, mixed>|null $raw */
    public function __construct(private readonly ?array $raw)
    {
    }

    public function load(string $type, string $id, ?string $themeSlug = null): ?array
    {
        ++$this->loads;

        return $this->raw;
    }
}
