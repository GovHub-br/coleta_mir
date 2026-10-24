<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ValidatedPrincipal
{
    /**
     * @param list<string> $authenticationMethods
     */
    public function __construct(
        public string $providerKey,
        public string $issuer,
        public string $subject,
        public string $displayName,
        public ?string $verifiedEmail,
        public DateTimeImmutable $authenticatedAt,
        public array $authenticationMethods,
        public ?AssuranceLevel $assuranceLevel,
    ) {
        if (
            $this->providerKey === ''
            || $this->issuer === ''
            || $this->subject === ''
            || $this->displayName === ''
        ) {
            throw new InvalidArgumentException(
                'Validated principal identity fields cannot be empty.',
            );
        }

        if (
            $this->verifiedEmail !== null
            && filter_var($this->verifiedEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('Verified email is invalid.');
        }
    }
}
