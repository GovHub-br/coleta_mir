<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\ClientConfiguration;
use JoomlaOidc\Component\OidcClient\Site\Contract\ConfigurationResolverInterface;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\RedirectUriMode;

final class EnvConfigurationResolver implements ConfigurationResolverInterface
{
    private const ENV_CLIENT_ID = 'OIDC_CLIENT_ID';
    private const ENV_CLIENT_SECRET = 'OIDC_CLIENT_SECRET';
    private const ENV_PUBLIC_BASE_URL = 'OIDC_PUBLIC_BASE_URL';
    private const ENV_REDIRECT_URI_MODE = 'OIDC_REDIRECT_URI_MODE';

    public function resolve(): ClientConfiguration
    {
        $clientId = $this->readEnv(self::ENV_CLIENT_ID);
        $clientSecret = $this->readEnv(self::ENV_CLIENT_SECRET);
        $baseUrl = $this->readEnv(self::ENV_PUBLIC_BASE_URL);
        $redirectUriMode = $this->readRedirectUriMode();

        $baseParts = parse_url($baseUrl);

        if (
            !\is_array($baseParts)
            || ($baseParts['scheme'] ?? null) !== 'https'
            || !isset($baseParts['host'])
            || isset($baseParts['user'])
            || isset($baseParts['pass'])
            || isset($baseParts['query'])
            || isset($baseParts['fragment'])
            || (($baseParts['path'] ?? '') !== '' && ($baseParts['path'] ?? '') !== '/')
        ) {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'OIDC_PUBLIC_BASE_URL must be an HTTPS origin without a path, query, or fragment.',
            );
        }

        $baseOrigin = rtrim($baseUrl, '/');

        if ($redirectUriMode === RedirectUriMode::SiteRoot) {
            $redirectUri = $baseOrigin . '/';
            $postLogoutRedirectUri = $baseOrigin . '/';
        } else {
            $redirectUri = $baseOrigin . '/index.php?option=com_oidcclient&task=callback.complete';
            $postLogoutRedirectUri = $baseOrigin . '/index.php?option=com_oidcclient&task=logout.completed';
        }

        try {
            return new ClientConfiguration(
                $clientId,
                $clientSecret,
                $redirectUri,
                $postLogoutRedirectUri,
            );
        } catch (\InvalidArgumentException $e) {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'Client configuration from environment is invalid: ' . $e->getMessage(),
                $e,
            );
        }
    }

    private function readEnv(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === '') {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                \sprintf('Required environment variable %s is not set or empty.', $name),
            );
        }

        return $value;
    }

    private function readRedirectUriMode(): RedirectUriMode
    {
        $value = $_SERVER[self::ENV_REDIRECT_URI_MODE]
            ?? $_ENV[self::ENV_REDIRECT_URI_MODE]
            ?? getenv(self::ENV_REDIRECT_URI_MODE);

        if ($value === false || $value === '') {
            return RedirectUriMode::ComponentRoute;
        }

        if (!\is_string($value)) {
            $value = '';
        }

        $mode = RedirectUriMode::tryFrom($value);

        if ($mode === null) {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'OIDC_REDIRECT_URI_MODE must be component_route or site_root.',
            );
        }

        return $mode;
    }
}
