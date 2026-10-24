<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use DateTimeImmutable;

final readonly class IdTokenClaims
{
    /**
     * @param list<string> $audience
     * @param list<string> $authenticationMethods
     */
    public function __construct(
        public string $issuer,
        public string $subject,
        public array $audience,
        public ?string $authorizedParty,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $authenticatedAt,
        public ?DateTimeImmutable $notBefore,
        public string $nonce,
        public string $displayName,
        public ?string $email,
        public bool $emailVerified,
        public array $authenticationMethods,
        public ?string $assuranceLevel,
    ) {
    }
}
