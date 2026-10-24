<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class ClientConfiguration
{
    private string $clientSecret;

    public function __construct(
        public string $clientId,
        #[SensitiveParameter]
        string $clientSecret,
        public string $redirectUri,
        public string $postLogoutRedirectUri,
    ) {
        if ($this->clientId === '' || $clientSecret === '') {
            throw new InvalidArgumentException(
                'OIDC client credentials cannot be empty.',
            );
        }

        $this->assertSecureUri($this->redirectUri, 'Redirect URI');
        $this->assertSecureUri(
            $this->postLogoutRedirectUri,
            'Post-logout redirect URI',
        );

        $this->clientSecret = $clientSecret;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    private function assertSecureUri(string $uri, string $label): void
    {
        $parts = parse_url($uri);

        if (
            filter_var($uri, FILTER_VALIDATE_URL) === false
            || !\is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                $label . ' must be an HTTPS URL without credentials or fragment.',
            );
        }
    }
}
