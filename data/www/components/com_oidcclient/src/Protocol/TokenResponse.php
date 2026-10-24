<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

final readonly class TokenResponse
{
    public function __construct(
        public string $idToken,
        public string $accessToken,
        public int $expiresIn,
    ) {
    }
}
