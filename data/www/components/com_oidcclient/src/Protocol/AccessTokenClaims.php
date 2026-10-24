<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

final readonly class AccessTokenClaims
{
    public function __construct(
        public string $subject,
    ) {
    }
}
