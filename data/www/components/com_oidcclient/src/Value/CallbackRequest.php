<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class CallbackRequest
{
    public function __construct(
        public string $state,
        public ?string $code = null,
        public ?string $error = null,
    ) {
        if (!preg_match('/^[A-Za-z0-9_-]{43,128}$/', $this->state)) {
            throw new InvalidArgumentException(
                'Callback state must be a valid base64url value.',
            );
        }

        $hasCode = $this->code !== null && $this->code !== '';
        $hasError = $this->error !== null && $this->error !== '';

        if ($hasCode === $hasError) {
            throw new InvalidArgumentException(
                'Callback must contain exactly one of code or error.',
            );
        }
    }
}
