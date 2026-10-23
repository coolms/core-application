<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Dashboard;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** A viewer whose answer a test can change between requests: one viewer, then the next. */
final class SwitchableChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        public bool $granted,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return $this->granted;
    }
}
