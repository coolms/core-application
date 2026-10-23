<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\ApiManifest\Fixture;

use CoolMS\Core\Application\ApiManifest\ApiManifestContributorInterface;
use CoolMS\Core\Application\ApiManifest\ApiManifestPatternTrait;
use CoolMS\Core\Application\ApiManifest\SectionApiManifest;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A module's manifest contributor, written the way a consumer writes one: the
 * collection routes are generated as they are, and each item route as a
 * pattern with an `{id}` token where the item's id goes.
 *
 * The placeholder is a constructor argument only so a test can choose one the
 * URL generator percent-encodes; a real contributor writes it inline.
 */
final readonly class SectionsManifestContributor implements ApiManifestContributorInterface
{
    use ApiManifestPatternTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $placeholder = '__id__',
    ) {
    }

    /**
     * @return array{string, SectionApiManifest}
     */
    public function contribute(): array
    {
        $gen = $this->urlGenerator;
        $id = ['id' => $this->placeholder];

        return ['sections', new SectionApiManifest(
            list: $gen->generate('sections_list'),
            create: $gen->generate('sections_create'),
            item: $this->pattern($gen, 'sections_get', $id, $this->placeholder, '{id}'),
            update: $this->pattern($gen, 'sections_update', $id, $this->placeholder, '{id}'),
            delete: $this->pattern($gen, 'sections_delete', $id, $this->placeholder, '{id}'),
        )];
    }
}
