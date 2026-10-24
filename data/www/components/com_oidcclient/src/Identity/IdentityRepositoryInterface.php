<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Identity;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;

interface IdentityRepositoryInterface
{
    /**
     * Returns null when no exact identity link exists.
     *
     * @throws OidcException With InternalError on repository failure.
     */
    public function findUserId(
        string $providerKey,
        string $issuer,
        string $subject,
    ): ?int;

    /**
     * @throws OidcException With IdentityConflict when either unique identity
     *                       constraint is already linked to another record.
     */
    public function link(
        int $userId,
        string $providerKey,
        string $issuer,
        string $subject,
    ): void;

    /** @throws OidcException With InternalError on repository failure. */
    public function recordLogin(int $userId, string $providerKey): void;
}
