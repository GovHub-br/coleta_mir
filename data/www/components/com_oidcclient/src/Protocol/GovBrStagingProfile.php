<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;

final class GovBrStagingProfile implements ProviderProfileInterface
{
    private const string ISSUER = 'https://sso.staging.acesso.gov.br/';

    public function key(): string
    {
        return 'govbr_staging';
    }

    public function issuer(): string
    {
        return self::ISSUER;
    }

    public function authorizationEndpoint(): string
    {
        return self::ISSUER . 'authorize';
    }

    public function tokenEndpoint(): string
    {
        return self::ISSUER . 'token';
    }

    public function userInfoEndpoint(): string
    {
        return self::ISSUER . 'userinfo';
    }

    public function jwksEndpoint(): string
    {
        return self::ISSUER . 'jwk';
    }

    public function logoutEndpoint(): string
    {
        return self::ISSUER . 'logout';
    }

    public function mandatoryScopes(): array
    {
        return ['openid', 'email', 'profile', 'govbr_confiabilidades'];
    }

    public function signingAlgorithms(): array
    {
        return ['RS256'];
    }
}
