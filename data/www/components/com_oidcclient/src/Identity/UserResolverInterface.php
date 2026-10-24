<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Identity;

use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;
use JoomlaOidc\Component\OidcClient\Site\Value\ValidatedPrincipal;

interface UserResolverInterface
{
    public function resolve(ValidatedPrincipal $principal): LocalUser;
}
