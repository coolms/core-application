<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Config;

use RuntimeException;

use function sprintf;

/**
 * The writer a host gets when no module owns a config store.
 *
 * The platform has callers that SAVE config -- the dashboard arrangement is
 * one -- so the port must always resolve or the container will not compile.
 * What it must not do is pretend: a host with nowhere to put an operator's
 * config should say so at the one moment someone is present to be told, not
 * report a save that went nowhere.
 *
 * `canWrite()` therefore answers false for everything, which is the honest
 * answer and the one a caller can act on; `write()` throws for a caller that
 * did not ask first.
 *
 * Replaced by whichever module owns the store (Settings, in a full CoolMS
 * install) -- see the core bundle's config-store defaults pass, which aliases
 * this ONLY when nothing else has claimed the port.
 */
final readonly class ReadOnlyConfigWriter implements ConfigWriterInterface
{
    public function canWrite(string $type, string $id): bool
    {
        return false;
    }

    public function write(string $type, string $id, array $data): string
    {
        throw new RuntimeException(sprintf('No config store is installed, so "%s/%s" cannot be saved.', $type, $id));
    }

    public function delete(string $type, string $id): bool
    {
        return false;
    }
}
