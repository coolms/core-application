<?php

declare(strict_types=1);

namespace CoolMS\CoreApp\ApiManifest;

final readonly class TerminalApiManifest
{
    public function __construct(
        public string $executeUrl,   // POST /api/v1/terminal/execute
        public string $completeUrl,  // POST /api/v1/terminal/complete
    ) {
    }
}
