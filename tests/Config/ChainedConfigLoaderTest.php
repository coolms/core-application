<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Config;

use CoolMS\Core\Application\Config\ChainedConfigLoader;
use CoolMS\Core\Application\Config\FileConfigLoader;
use CoolMS\Core\Config\ConfigOverrideReaderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;

use function array_map;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Reading config with the stored overrides layered over the files.
 *
 * Without this the write path is a no-op nobody notices until production: an
 * edit saved to the database on a read-only host would never be read back, and
 * the screen would go on showing the file it was meant to replace.
 */
#[CoversClass(ChainedConfigLoader::class)]
final class ChainedConfigLoaderTest extends TestCase
{
    #[Test]
    public function aStoredOverrideWins(): void
    {
        $loader = $this->loader(['type' => 'dashboard', 'widgets' => ['saved']]);

        self::assertSame(['type' => 'dashboard', 'widgets' => ['saved']], $loader->load('dashboard', 'main'));
    }

    /**
     * The normal case, and the one that matters for every existing consumer:
     * until something writes an override, this behaves exactly like the file
     * loader it replaced as `ConfigLoaderInterface`.
     *
     * !! The unmigrated-database case is NOT tested here -- it lives with the
     * catch in whichever module implements the port, the only layer allowed to
     * name a persistence exception. From here that case is simply "the port
     * returned null", which is this test.
     */
    #[Test]
    public function withNoOverrideItFallsThroughToTheFiles(): void
    {
        // An empty temp dir, so the file loader genuinely finds nothing rather
        // than being stubbed into saying so.
        self::assertNull($this->loader(null)->load('dashboard', 'main'));
    }

    /**
     * The port the platform holds can only READ. Worth asserting as a shape
     * rather than as behaviour: a loader given a store it could write through
     * is one refactor away from upserting a "default" row on first read, which
     * would quietly turn a shipped file into a stored row on every fresh
     * install with nothing about the screen looking different.
     */
    #[Test]
    public function theReadPortOffersNoWayToWrite(): void
    {
        $port = new ReflectionClass(ConfigOverrideReaderInterface::class);

        self::assertSame(
            ['findOverrideData'],
            array_map(static fn (ReflectionMethod $m): string => $m->getName(), $port->getMethods()),
        );
    }

    /**
     * @param array<string, mixed>|null $override
     */
    private function loader(?array $override): ChainedConfigLoader
    {
        return new ChainedConfigLoader($this->files(), $this->overrides($override));
    }

    private function files(): FileConfigLoader
    {
        return new FileConfigLoader(sys_get_temp_dir() . '/coolms-config-' . uniqid(), new NullLogger());
    }

    /**
     * @param array<string, mixed>|null $override
     */
    private function overrides(?array $override): ConfigOverrideReaderInterface
    {
        return new class($override) implements ConfigOverrideReaderInterface {
            /**
             * @param array<string, mixed>|null $override
             */
            public function __construct(private readonly ?array $override)
            {
            }

            public function findOverrideData(string $type, string $id): ?array
            {
                return $this->override;
            }
        };
    }
}
