<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;

final class GovBrProfileRegistry
{
    public function get(string $profileKey): ProviderProfileInterface
    {
        return match ($profileKey) {
            'govbr_staging' => new GovBrStagingProfile(),
            'govbr_production' => new GovBrProductionProfile(),
            default => throw new \InvalidArgumentException('Unsupported GOV.BR provider profile.'),
        };
    }
}
