<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\ApiManifest;

use CoolMS\Core\Application\ApiManifest\ApiManifestBuilder;
use CoolMS\Core\Application\ApiManifest\ApiManifestContributorInterface;
use CoolMS\Core\Config\PlatformDefaults;
use CoolMS\Core\Config\SupportedLocalesProvider;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The builder threads Core-owned platform data
 * (supported locales + platform defaults) straight into the manifest,
 * without a contributor.
 *
 * This pins the new `platformDefaults` wiring; the manifest is what the
 * FE reads to render anonymous / pre-login views with the deployment's
 * real locale + formats.
 */
final class ApiManifestBuilderTest extends TestCase
{
    #[Test]
    public function manifestCarriesPlatformDefaults(): void
    {
        $platformDefaults = new PlatformDefaults(
            locale: 'uk',
            timezone: 'Europe/Kyiv',
            dateFormat: 'dd/MM/yyyy',
            timeFormat: '24h',
            weekStart: 'monday',
        );

        $builder = new ApiManifestBuilder(
            contributors: [],
            localesProvider: new SupportedLocalesProvider([['code' => 'uk', 'label' => 'Українська']]),
            platformDefaults: $platformDefaults,
        );

        $manifest = $builder->build('/api/v1');

        self::assertSame($platformDefaults, $manifest->platformDefaults);
        // The default locale, previously absent from the manifest, now
        // rides along on platformDefaults. (assertSame above narrows it
        // to non-null, so a plain -> here, not ?->.)
        self::assertSame('uk', $manifest->platformDefaults->locale);
        // Sibling Core data still flows.
        self::assertSame([['code' => 'uk', 'label' => 'Українська']], $manifest->supportedLocales);
    }

    #[Test]
    public function aModuleBlockLandsInTheModulesMapUnderItsName(): void
    {
        $manifest = $this->builder(
            self::contributing('modules.section', ['siteScriptSources' => '/api/v1/site/scripts']),
            self::contributing('modules.chat', ['rooms' => '/api/v1/chat/rooms']),
            self::contributing('editor', (object) ['toolbar' => []]),
        )->build('/api/v1');

        self::assertSame([
            'section' => ['siteScriptSources' => '/api/v1/site/scripts'],
            'chat' => ['rooms' => '/api/v1/chat/rooms'],
        ], $manifest->modules);
        self::assertEquals((object) ['toolbar' => []], $manifest->editor);
    }

    #[Test]
    public function noModuleBlockLeavesTheMapEmpty(): void
    {
        self::assertSame([], $this->builder()->build('/api/v1')->modules);
    }

    #[Test]
    public function anUnknownKeyIsRefusedNamingItsContributor(): void
    {
        $contributor = self::contributing('siteScriptSources', ['/api/v1/site/scripts']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($contributor::class . ' contributes the unknown manifest key "siteScriptSources"');

        $this->builder($contributor)->build('/api/v1');
    }

    #[Test]
    public function aModuleBlockGivenTwiceIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('contributes "modules.section", which another contributor already gave.');

        $this->builder(self::contributing('modules.section', []), self::contributing('modules.section', []))
            ->build('/api/v1');
    }

    #[Test]
    public function aCoreSectionGivenTwiceIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('contributes "editor", which another contributor already gave.');

        $this->builder(self::contributing('editor', (object) []), self::contributing('editor', (object) []))
            ->build('/api/v1');
    }

    #[Test]
    public function aModuleBlockNamingNoModuleIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('contributes "modules.", a module block that names no module.');

        $this->builder(self::contributing('modules.', []))->build('/api/v1');
    }

    private static function contributing(string $key, mixed $value): ApiManifestContributorInterface
    {
        return new class($key, $value) implements ApiManifestContributorInterface {
            public function __construct(private readonly string $key, private readonly mixed $value)
            {
            }

            public function contribute(): array
            {
                return [$this->key, $this->value];
            }
        };
    }

    private function builder(ApiManifestContributorInterface ...$contributors): ApiManifestBuilder
    {
        return new ApiManifestBuilder(
            contributors: $contributors,
            localesProvider: new SupportedLocalesProvider([]),
            platformDefaults: new PlatformDefaults(
                locale: 'en',
                timezone: 'UTC',
                dateFormat: 'yyyy-MM-dd',
                timeFormat: '24h',
                weekStart: 'monday',
            ),
        );
    }
}
