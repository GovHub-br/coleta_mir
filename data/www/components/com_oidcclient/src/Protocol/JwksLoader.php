<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use Jose\Component\Core\JWKSet;
use JoomlaOidc\Component\OidcClient\Site\Contract\HttpClientInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\JwksCacheInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class JwksLoader
{
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JwksCacheInterface $cache,
    ) {
    }

    public function load(
        ProviderProfileInterface $profile,
        ?string $requiredKid = null,
    ): JWKSet {
        $providerKey = $profile->key();

        $jwksJson = $this->cache->get($providerKey);

        if ($jwksJson !== null) {
            try {
                $jwkset = $this->parseJwks($jwksJson);

                if ($requiredKid === null || $this->hasKeyWithKid($jwkset, $requiredKid)) {
                    return $jwkset;
                }
            } catch (OidcException) {
                $this->cache->forget($providerKey);
                $jwksJson = null;
            }
        }

        $fetchedJson = $this->fetch($profile);

        if ($fetchedJson === $jwksJson) {
            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                'JWKS unchanged after refresh; requested kid not found.',
                failureCategory: OidcFailureCategory::JwksContentUnchanged,
            );
        }

        $jwkset = $this->parseJwks($fetchedJson);

        if ($requiredKid !== null && !$this->hasKeyWithKid($jwkset, $requiredKid)) {
            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                \sprintf('JWKS does not contain requested kid: %s.', $requiredKid),
                failureCategory: OidcFailureCategory::JwksKidNotFound,
            );
        }

        $this->cache->put(
            $providerKey,
            $fetchedJson,
            self::CACHE_TTL_SECONDS,
        );

        return $jwkset;
    }

    private function fetch(ProviderProfileInterface $profile): string
    {
        $request = new HttpRequest(
            method: 'GET',
            url: $profile->jwksEndpoint(),
        );

        $response = $this->httpClient->request($request);

        if ($response->statusCode !== 200) {
            $category = match (true) {
                $response->statusCode >= 300 && $response->statusCode <= 399 => OidcFailureCategory::JwksHttp3xx,
                $response->statusCode >= 400 && $response->statusCode <= 499 => OidcFailureCategory::JwksHttp4xx,
                $response->statusCode >= 500 && $response->statusCode <= 599 => OidcFailureCategory::JwksHttp5xx,
                default => OidcFailureCategory::JwksHttpOther,
            };

            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                \sprintf('JWKS endpoint returned HTTP %d.', $response->statusCode),
                failureCategory: $category,
            );
        }

        return $response->body;
    }

    private function parseJwks(string $json): JWKSet
    {
        try {
            $jwkset = JWKSet::createFromJson($json);

            if ($jwkset->all() === []) {
                throw new \InvalidArgumentException('JWKS contains no keys.');
            }

            return $jwkset;
        } catch (\Throwable $e) {
            throw new OidcException(
                OidcErrorCode::JwksUnavailable,
                'Failed to parse JWKS JSON.',
                $e,
                OidcFailureCategory::JwksMalformed,
            );
        }
    }

    private function hasKeyWithKid(JWKSet $jwkset, string $kid): bool
    {
        foreach ($jwkset->all() as $jwk) {
            if ($jwk->has('kid') && $jwk->get('kid') === $kid) {
                return true;
            }
        }

        return false;
    }
}
