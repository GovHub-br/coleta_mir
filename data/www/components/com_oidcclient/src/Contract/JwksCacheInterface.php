<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;

interface JwksCacheInterface
{
    /**
     * Returns null when an entry is missing or expired. Expired content must
     * not be returned as a stale fallback.
     *
     * @throws OidcException With JwksUnavailable on cache infrastructure error.
     */
    public function get(string $providerKey): ?string;

    /** @throws OidcException With JwksUnavailable. */
    public function put(string $providerKey, string $jwksJson, int $ttl): void;

    /** @throws OidcException With JwksUnavailable. */
    public function forget(string $providerKey): void;
}
