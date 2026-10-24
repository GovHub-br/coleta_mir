<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use JoomlaOidc\Component\OidcClient\Site\Value\ClientConfiguration;

interface ConfigurationResolverInterface
{
    public function resolve(): ClientConfiguration;
}
