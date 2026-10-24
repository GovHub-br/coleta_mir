<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProfileRegistry;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final readonly class EnvProviderProfileResolver
{
    private const string ENV_PROVIDER_PROFILE = 'OIDC_PROVIDER_PROFILE';

    public function __construct(
        private GovBrProfileRegistry $profiles,
    ) {
    }

    public function resolve(): ProviderProfileInterface
    {
        $profileKey = $_SERVER[self::ENV_PROVIDER_PROFILE]
            ?? $_ENV[self::ENV_PROVIDER_PROFILE]
            ?? getenv(self::ENV_PROVIDER_PROFILE);

        if (!\is_string($profileKey) || $profileKey === '') {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'Required environment variable OIDC_PROVIDER_PROFILE is not set or empty.',
            );
        }

        try {
            return $this->profiles->get($profileKey);
        } catch (\InvalidArgumentException $exception) {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'OIDC_PROVIDER_PROFILE must be govbr_staging or govbr_production.',
                $exception,
            );
        }
    }
}
