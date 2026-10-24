<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use Joomla\CMS\Cache\CacheController;
use JoomlaOidc\Component\OidcClient\Site\Contract\JwksCacheInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final class JoomlaJwksCache implements JwksCacheInterface
{
    private const CACHE_KEY_PREFIX = 'oidc_jwks_';

    public function __construct(private readonly CacheController $cache)
    {
    }

    public function get(string $providerKey): ?string
    {
        $key = $this->cacheKey($providerKey);
        $cached = $this->cache->get($key);

        if ($cached === false || $cached === null) {
            return null;
        }

        return $cached;
    }

    public function put(string $providerKey, string $jwksJson, int $ttl): void
    {
        $key = $this->cacheKey($providerKey);

        try {
            $this->cache->setLifeTime($ttl);
            $this->cache->store($jwksJson, $key);
        } catch (\RuntimeException $e) {
            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                'Failed to store JWKS in cache: ' . $e->getMessage(),
                $e,
            );
        }
    }

    public function forget(string $providerKey): void
    {
        $key = $this->cacheKey($providerKey);

        try {
            $this->cache->remove($key);
        } catch (\RuntimeException $e) {
            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                'Failed to evict JWKS from cache: ' . $e->getMessage(),
                $e,
            );
        }
    }

    private function cacheKey(string $providerKey): string
    {
        return self::CACHE_KEY_PREFIX . $providerKey;
    }
}
