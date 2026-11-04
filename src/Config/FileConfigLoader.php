<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Config;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Filesystem implementation of ConfigLoaderInterface.
 *
 * Resolution order:
 *   1. Scan config/modules/* (two levels) for YAML matching type+id fields
 *   2. Merge config/themes/{themeSlug} override on top when themeSlug given
 *
 * Returns null when no matching config found.
 *
 * Each root's files are indexed by type+id once, not parsed again on every
 * call: a caller reading many ids (the settings list reads one per block, most
 * with no file) would otherwise parse every YAML under the root each time. The
 * index holds which file answers an id, and that an id has none. It is kept
 * while the root's files are the same (paths, modification times and sizes),
 * so a file added, removed or edited -- by a writer in this process too -- is
 * seen by the next call. A hit is read from its file afresh and checked to
 * still match, so an index never answers with old content.
 */
final class FileConfigLoader implements ConfigLoaderInterface
{
    /** @var array<string, array{signature: string, paths: array<string, string>}> */
    private array $indexes = [];

    /**
     * @param list<string> $moduleConfigDirs every registered bundle's config/
     *                                       that carries module YAML, newest
     *                                       from ModuleConfigDirsPass
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/config')]
        private readonly string $configDir,
        private readonly LoggerInterface $logger,
        #[Autowire('%coolms.module_config_dirs%')]
        private readonly array $moduleConfigDirs = [],
    ) {
    }

    public function load(string $type, string $id, ?string $themeSlug = null): ?array
    {
        $base = null;
        foreach ($this->roots() as $root) {
            $base = $this->scanForConfig($root . '/modules', $type, $id);
            if (null !== $base) {
                break;
            }
        }

        if (null !== $themeSlug) {
            $override = $this->scanForConfig($this->configDir . '/themes/' . $themeSlug, $type, $id);
            if (null !== $override) {
                $base = null !== $base ? array_merge_recursive($base, $override) : $override;
            }
        }

        return $base;
    }

    /**
     * The PATH of the file this type+id would be read from, or null.
     *
     * Exists so a writer can put an edit back where the reader will find it. It
     * is the same scan as {@see self::scanForConfig()} stopping one step
     * earlier, and it lives here rather than in the writer for one reason: two
     * copies of "which file is this config" drift, and the day they disagree an
     * admin's edit lands in a file nothing loads. Only `config/modules` is
     * searched -- a theme override is a layer on top, never the thing you edit.
     */
    public function locate(string $type, string $id): ?string
    {
        return $this->find($this->configDir . '/modules', $type, $id)[0] ?? null;
    }

    /**
     * Roots to scan, application FIRST.
     *
     * This loader returns the first match, so first means highest priority: an
     * installation can override a definition a package ships by putting its own
     * file in `config/modules`, and never the other way round.
     *
     * @return list<string>
     */
    private function roots(): array
    {
        $roots = [$this->configDir];
        foreach ($this->moduleConfigDirs as $dir) {
            $roots[] = $dir;
        }

        return $roots;
    }

    /**
     * Scan all YAML files two levels below the given root (root/{module}/{type-dir}/{file}.yaml).
     * Match the first file whose 'type' equals $type AND whose 'id' (or 'tree') equals $id.
     *
     * @return array<string, mixed>|null
     */
    private function scanForConfig(string $root, string $type, string $id): ?array
    {
        return $this->find($root, $type, $id)[1] ?? null;
    }

    /**
     * The first file below $root whose type+id match, and its data; [] when none does.
     *
     * @return array{0: string, 1: array<mixed>}|array{}
     */
    private function find(string $root, string $type, string $id): array
    {
        $files = glob($root . '/*/*/*.yaml') ?: [];
        $paths = $this->index($root, $files);
        $file = $paths[$this->key($type, $id)] ?? null;
        if (null === $file) {
            return [];
        }

        $data = $this->parse($file);
        if (null !== $data && $this->matches($data, $type, $id)) {
            return [$file, $data];
        }

        // The file changed in a way its size and time did not show: index the root again and look once more.
        unset($this->indexes[$root]);
        $file = $this->index($root, $files)[$this->key($type, $id)] ?? null;
        $data = null !== $file ? $this->parse($file) : null;

        return null !== $file && null !== $data && $this->matches($data, $type, $id) ? [$file, $data] : [];
    }

    /**
     * Which file answers each type+id below $root, the first in the files' order; built again when the files differ.
     *
     * @param list<string> $files
     *
     * @return array<string, string>
     */
    private function index(string $root, array $files): array
    {
        // A file's time is whole seconds: one written in the last second could change again unseen, so its
        // content is part of the signature too (how git treats a racily clean file).
        clearstatcache();
        $recent = time() - 1;
        $signature = '';
        foreach ($files as $file) {
            $stat = @stat($file);
            $signature .= $file . "\0" . (false === $stat ? '-' : $stat['mtime'] . ':' . $stat['size']);
            if (false !== $stat && $stat['mtime'] >= $recent) {
                $signature .= ':' . (hash_file('xxh128', $file) ?: '-');
            }
            $signature .= "\n";
        }
        $signature = hash('xxh128', $signature);

        if (($this->indexes[$root]['signature'] ?? null) === $signature) {
            return $this->indexes[$root]['paths'];
        }

        $paths = [];
        foreach ($files as $file) {
            $data = $this->parse($file);
            $type = $data['type'] ?? null;
            $id = $data['id'] ?? $data['tree'] ?? null;
            if (is_string($type) && is_string($id)) {
                $paths[$this->key($type, $id)] ??= $file;
            }
        }
        $this->indexes[$root] = ['signature' => $signature, 'paths' => $paths];

        return $paths;
    }

    /** @return array<mixed>|null the file's data, or null when it is unreadable YAML or not a map */
    private function parse(string $file): ?array
    {
        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $e) {
            $this->logger->warning('ConfigLoader: YAML parse error in {file}: {error}', [
                'file' => $file,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function key(string $type, string $id): string
    {
        return $type . "\0" . $id;
    }

    /** @param array<mixed> $data */
    private function matches(array $data, string $type, string $id): bool
    {
        if (($data['type'] ?? null) !== $type) {
            return false;
        }

        // navigraph type uses 'tree' as identifier; everything else uses 'id'
        return ($data['id'] ?? $data['tree'] ?? null) === $id;
    }
}
