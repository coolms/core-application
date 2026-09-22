<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Config;

use CoolMS\Core\Config\ConfigOverrideReaderInterface;

/**
 * The reader a host gets when nothing stores config overrides.
 *
 * Config is read on every request, so the chain cannot be left without one:
 * this is the answer "nobody has overridden anything", which is exactly what
 * the platform got before a store existed, and is the truth on a host that
 * installs no module able to save one.
 *
 * Replaced -- not decorated -- by whichever module owns the override rows. See
 * the core bundle's config-store defaults pass, which aliases this ONLY when
 * nothing else has claimed the port.
 */
final readonly class NoConfigOverrides implements ConfigOverrideReaderInterface
{
    public function findOverrideData(string $type, string $id): ?array
    {
        return null;
    }
}
