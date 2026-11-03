<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\ApiManifest;

use CoolMS\Core\Config\PlatformDefaults;
use CoolMS\Core\Config\SupportedLocalesProvider;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function array_key_exists;
use function in_array;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Assembles ApiManifest from all registered ApiManifestContributorInterface implementations.
 *
 * Contributors are collected via the 'coolms.api_manifest_contributor' DI tag,
 * added through registerForAutoconfiguration() in Core's DI Extension.
 *
 * Two pieces of Core-owned platform data -- `SupportedLocalesProvider`
 * and `PlatformDefaults` -- are injected DIRECTLY rather than via the
 * contributor tag: they are Core's own and need no module indirection.
 */
final class ApiManifestBuilder
{
    /** The sections Core types; any other key a contributor returns is refused. */
    public const array CORE_KEYS = [
        'auth', 'sections', 'navi', 'content', 'dataGrid', 'terminal', 'media', 'document', 'vfs', 'dynamicEntity',
        'identity', 'domainExplorer', 'editor', 'viewers', 'ui',
    ];

    /** A contribution keyed `modules.<module>` is that module's own block in the manifest's `modules` map. */
    public const string MODULES_PREFIX = 'modules.';

    /**
     * @param iterable<ApiManifestContributorInterface> $contributors
     */
    public function __construct(
        #[AutowireIterator('coolms.api_manifest_contributor')]
        private readonly iterable $contributors,
        private readonly SupportedLocalesProvider $localesProvider,
        private readonly PlatformDefaults $platformDefaults,
    ) {
    }

    public function build(string $apiBase): ApiManifest
    {
        $sections = [];
        $modules = [];

        foreach ($this->contributors as $contributor) {
            $contribution = $contributor->contribute();
            if (null === $contribution) {
                continue;
            }
            [$key, $value] = $contribution;
            if (str_starts_with($key, self::MODULES_PREFIX)) {
                $module = substr($key, strlen(self::MODULES_PREFIX));
                if ('' === $module) {
                    throw new LogicException(sprintf('%s contributes "%s", a module block that names no module.', $contributor::class, $key));
                }
                if (array_key_exists($module, $modules)) {
                    throw new LogicException(sprintf('%s contributes "%s", which another contributor already gave.', $contributor::class, $key));
                }
                $modules[$module] = $value;

                continue;
            }
            if (!in_array($key, self::CORE_KEYS, true)) {
                throw new LogicException(sprintf('%s contributes the unknown manifest key "%s": a module\'s own block goes under "%s<module>".', $contributor::class, $key, self::MODULES_PREFIX));
            }
            if (array_key_exists($key, $sections)) {
                throw new LogicException(sprintf('%s contributes "%s", which another contributor already gave.', $contributor::class, $key));
            }
            $sections[$key] = $value;
        }

        /** @var ?AuthApiManifest $auth */
        $auth = $sections['auth'] ?? null;
        /** @var ?SectionApiManifest $sectionManifest */
        $sectionManifest = $sections['sections'] ?? null;
        /** @var ?NaviApiManifest $navi */
        $navi = $sections['navi'] ?? null;
        /** @var ?ContentApiManifest $content */
        $content = $sections['content'] ?? null;
        /** @var ?DataGridApiManifest $dataGrid */
        $dataGrid = $sections['dataGrid'] ?? null;
        /** @var ?TerminalApiManifest $terminal */
        $terminal = $sections['terminal'] ?? null;
        /** @var ?MediaApiManifest $media */
        $media = $sections['media'] ?? null;
        /** @var ?DocumentApiManifest $document */
        $document = $sections['document'] ?? null;
        /** @var ?VfsApiManifest $vfs */
        $vfs = $sections['vfs'] ?? null;
        /** @var ?DynamicEntityApiManifest $dynamicEntity */
        $dynamicEntity = $sections['dynamicEntity'] ?? null;

        return new ApiManifest(
            apiBase: $apiBase,
            configBase: $apiBase . '/config',
            auth: $auth,
            sections: $sectionManifest,
            navi: $navi,
            content: $content,
            dataGrid: $dataGrid,
            terminal: $terminal,
            media: $media,
            document: $document,
            vfs: $vfs,
            dynamicEntity: $dynamicEntity,
            identity: $sections['identity'] ?? null,
            supportedLocales: $this->localesProvider->all(),
            platformDefaults: $this->platformDefaults,
            domainExplorer: $sections['domainExplorer'] ?? null,
            editor: $sections['editor'] ?? null,
            viewers: $sections['viewers'] ?? null,
            ui: $sections['ui'] ?? null,
            modules: $modules,
        );
    }
}
