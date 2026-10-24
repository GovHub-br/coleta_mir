<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class LocalUser
{
    public function __construct(
        public int $id,
        public string $username,
        public string $displayName,
        public ?string $email,
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException('Local user ID must be positive.');
        }

        if ($this->username === '' || $this->displayName === '') {
            throw new InvalidArgumentException(
                'Local user identity fields cannot be empty.',
            );
        }
    }
}
