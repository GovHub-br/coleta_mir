<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LoginTransaction
{
    public function __construct(
        public string $providerKey,
        public string $nonce,
        public string $pkceVerifier,
        public string $returnUrl,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {
        if (
            $this->providerKey === ''
            || $this->returnUrl === ''
        ) {
            throw new InvalidArgumentException(
                'Login transaction fields cannot be empty.',
            );
        }

        if (!preg_match('/^[A-Za-z0-9_-]{43,128}$/', $this->nonce)) {
            throw new InvalidArgumentException(
                'Nonce must be a valid base64url value.',
            );
        }

        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $this->pkceVerifier)) {
            throw new InvalidArgumentException(
                'PKCE verifier does not meet RFC 7636 requirements.',
            );
        }

        if ($this->expiresAt <= $this->createdAt) {
            throw new InvalidArgumentException(
                'Login transaction must expire after it is created.',
            );
        }
    }
}
