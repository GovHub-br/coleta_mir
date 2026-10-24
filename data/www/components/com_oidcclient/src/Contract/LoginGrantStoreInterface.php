<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;

interface LoginGrantStoreInterface
{
    /** @throws OidcException With InvalidGrant when issue fails. */
    public function issue(LocalUser $user): string;

    /**
     * @throws OidcException With InvalidGrant when unknown or reused, or
     *                       ExpiredGrant when expired.
     */
    public function consume(string $rawGrant): LocalUser;
}
