<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Service;

use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;
use JoomlaOidc\Component\OidcClient\Site\Value\ValidatedPrincipal;

final readonly class ResolvedLogin
{
    public function __construct(
        public ValidatedPrincipal $principal,
        public LocalUser $localUser,
        public string $returnUrl,
    ) {
        if ($this->returnUrl === '') {
            throw new \InvalidArgumentException('Resolved login return URL cannot be empty.');
        }
    }
}
