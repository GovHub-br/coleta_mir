<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class AuthorizationRedirect
{
    public function __construct(public string $url)
    {
        $parts = parse_url($this->url);

        if (
            filter_var($this->url, FILTER_VALIDATE_URL) === false
            || !\is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'Authorization URL must be an HTTPS URL without credentials.',
            );
        }
    }
}
