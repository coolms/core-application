<?php

declare(strict_types=1);

namespace CoolMS\CoreModule\ApiManifest;

/**
 * Document module's manifest section -- emitted by
 * the document module's manifest contributor
 * under the `document` key in `GET /api/v1/theme/config`.
 *
 * Today the only entry is `spacesUrl` (used by the FE
 * DocumentSpaceAccordion to fetch the per-user space list); the
 * struct is reserved so future Document-module URLs (template list,
 * generation, ...) can land here without a new manifest section.
 */
final readonly class DocumentApiManifest
{
    public function __construct(
        public string $spacesUrl = '',     // GET /api/v1/document/spaces
        /**
         * !! Carried in the manifest rather than derived by the FE from
         * `spacesUrl`. String-concatenating `/available` onto it would couple
         * the client to a URL shape the router owns, and would keep working
         * until the route moved -- then fail as a 404 the FE reports as "no
         * sites available", which is indistinguishable from the true empty
         * answer.
         */
        public string $spacesAvailableUrl = '',   // GET  /api/v1/document/spaces/available
        public string $spaceEnablementUrl = '',   // POST /api/v1/document/spaces/enablement
        /**
         * FQCN of the entity a filter-mode audience can be built from.
         *
         * Warning: Carried here because the admin needs it and must not know
         * it. The generation wizard offers Filter mode only when a template's
         * context schema references this type, and it decided that from a
         * constant compiled into the published admin bundle -- which put the
         * consuming application's class name inside an npm package, and made
         * that package work only against an installation that has the class.
         *
         * A string rather than anything typed: this package cannot name the
         * application's entity, which is the whole point. The server owns the
         * value and the client compares it.
         *
         * Empty means the server did not say. The client must treat that as
         * "filter mode unavailable" rather than falling back to a guess -- an
         * absent answer and a wrong answer are not the same, and only one of
         * them is safe to act on.
         */
        public string $filterAudienceEntity = '',
    ) {
    }
}
