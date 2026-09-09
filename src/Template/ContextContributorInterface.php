<?php

declare(strict_types=1);

namespace CoolMS\CoreApp\Template;

use CoolMS\Core\Template\ContextContributorInterface as CoreContextContributorInterface;

/**
 * The context-enrichment SPI, kept at this name so consumers typed against it
 * keep working. The contract itself is
 * {@see CoreContextContributorInterface} in `coolms/core`.
 *
 * !! IT MOVED DOWN, NOT SIDEWAYS. Three interfaces in `coolms/core` extend
 * this contract -- `Web\TemplateContextContributorInterface`,
 * `Document\DocumentContextContributorInterface` and
 * `Document\DocumentRenderContextContributorInterface` -- so while it lived
 * here, the contracts package imported a package that requires it back. That
 * is the one dependency shape a `composer require` cannot fix: declaring it
 * would have written the cycle down rather than removed it.
 *
 * Nothing about the contract needed this tier. It declares one method over
 * plain arrays and names no type at all.
 *
 * Prefer the `CoolMS\Core\Template` name in new code. This one is a subtype
 * with nothing added, so an implementation of either satisfies a parameter
 * typed against the parent.
 */
interface ContextContributorInterface extends CoreContextContributorInterface
{
}
