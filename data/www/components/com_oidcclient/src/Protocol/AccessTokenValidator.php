<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\ClientConfiguration;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class AccessTokenValidator
{
    private const int CLOCK_SKEW_SECONDS = 30;

    private readonly AlgorithmManager $algorithmManager;
    private readonly JWSVerifier $jwsVerifier;
    private readonly JWSSerializerManager $serializerManager;

    public function __construct()
    {
        $this->algorithmManager = new AlgorithmManager([new RS256()]);
        $this->jwsVerifier = new JWSVerifier($this->algorithmManager);
        $this->serializerManager = new JWSSerializerManager([new CompactSerializer()]);
    }

    public function validateIfJwt(
        string $accessToken,
        JWKSet $jwkset,
        ProviderProfileInterface $profile,
        ClientConfiguration $clientConfig,
        ClockInterface $clock,
    ): ?AccessTokenClaims {
        if (!str_contains($accessToken, '.')) {
            return null;
        }

        $jws = $this->unserialize($accessToken);

        $key = $this->selectKey($jws, $jwkset, $profile);
        $this->verifySignature($jws, $key);

        $payload = $this->decodePayload($jws);

        return $this->parseClaims($payload, $profile, $clientConfig, $clock);
    }

    private function unserialize(string $accessToken): \Jose\Component\Signature\JWS
    {
        try {
            return $this->serializerManager->unserialize($accessToken);
        } catch (\Throwable $e) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Failed to unserialize access token as JWT.',
                $e,
                OidcFailureCategory::AccessTokenMalformed,
            );
        }
    }

    private function verifySignature(
        \Jose\Component\Signature\JWS $jws,
        JWK $jwk,
    ): void {
        try {
            $verified = $this->jwsVerifier->verifyWithKey($jws, $jwk, 0);
        } catch (\Throwable $e) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token JWS verification threw an exception.',
                $e,
                OidcFailureCategory::AccessTokenSignatureInvalid,
            );
        }

        if (!$verified) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token JWS signature verification failed.',
                null,
                OidcFailureCategory::AccessTokenSignatureInvalid,
            );
        }
    }

    private function selectKey(
        \Jose\Component\Signature\JWS $jws,
        JWKSet $jwkset,
        ProviderProfileInterface $profile,
    ): JWK {
        $header = $jws->getSignature(0)->getProtectedHeader();
        $algorithm = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;

        if (!\is_string($algorithm) || !\in_array($algorithm, $profile->signingAlgorithms(), true)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token uses an unsupported algorithm.',
                failureCategory: OidcFailureCategory::AccessTokenAlgorithmInvalid,
            );
        }

        if (!\is_string($kid) || $kid === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token is missing kid.',
                failureCategory: OidcFailureCategory::AccessTokenKidMissing,
            );
        }

        $matches = [];

        foreach ($jwkset->all() as $jwk) {
            if (!$jwk->has('kid') || $jwk->get('kid') !== $kid) {
                continue;
            }

            if ($jwk->has('use') && $jwk->get('use') !== 'sig') {
                continue;
            }

            if ($jwk->has('alg') && $jwk->get('alg') !== $algorithm) {
                continue;
            }

            if ($jwk->has('key_ops') && !\in_array('verify', (array) $jwk->get('key_ops'), true)) {
                continue;
            }

            if ($jwk->has('kty') && $jwk->get('kty') === 'RSA') {
                $matches[] = $jwk;
            }
        }

        if (\count($matches) !== 1) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token kid did not resolve to one signing key.',
                null,
                OidcFailureCategory::AccessTokenSigningKeyInvalid,
            );
        }

        return $matches[0];
    }

    private function decodePayload(\Jose\Component\Signature\JWS $jws): string
    {
        $payload = $jws->getPayload();

        if ($payload === null || $payload === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token has empty payload.',
                null,
                OidcFailureCategory::AccessTokenClaimsInvalid,
            );
        }

        return $payload;
    }

    private function parseClaims(
        string $payload,
        ProviderProfileInterface $profile,
        ClientConfiguration $clientConfig,
        ClockInterface $clock,
    ): AccessTokenClaims {
        $data = json_decode($payload, true);

        if (!\is_array($data)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token payload is not valid JSON.',
                null,
                OidcFailureCategory::AccessTokenPayloadInvalidJson,
            );
        }

        foreach (['iss', 'sub', 'aud', 'exp', 'iat'] as $requiredClaim) {
            if (!\array_key_exists($requiredClaim, $data)) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    \sprintf('Access token missing required claim: %s.', $requiredClaim),
                    null,
                    OidcFailureCategory::AccessTokenMissingRequiredClaim,
                );
            }
        }

        if (!\is_string($data['sub']) || $data['sub'] === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token sub must be a non-empty string.',
                null,
                OidcFailureCategory::AccessTokenClaimTypeInvalid,
            );
        }

        if (!\is_string($data['iss'])) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token iss must be a string.',
                null,
                OidcFailureCategory::AccessTokenClaimTypeInvalid,
            );
        }

        if ($data['iss'] !== $profile->issuer()) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token issuer mismatch.',
                null,
                OidcFailureCategory::AccessTokenIssuerMismatch,
            );
        }

        $audience = \is_array($data['aud']) ? $data['aud'] : [$data['aud']];

        if ($audience === []) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token audience cannot be empty.',
                failureCategory: OidcFailureCategory::AccessTokenAudienceInvalid,
            );
        }

        foreach ($audience as $value) {
            if (!\is_string($value) || $value === '') {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'Access token audience must contain strings.',
                    failureCategory: OidcFailureCategory::AccessTokenAudienceInvalid,
                );
            }
        }

        if (!\in_array($clientConfig->clientId, $audience, true)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token audience does not include the configured client ID.',
                null,
                OidcFailureCategory::AccessTokenAudienceMismatch,
            );
        }

        $expiration = $this->numericDate($data['exp'], 'exp');
        $issuedAt = $this->numericDate($data['iat'], 'iat');
        $now = $clock->now()->getTimestamp();

        if ($now > $expiration + self::CLOCK_SKEW_SECONDS) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token has expired.',
                null,
                OidcFailureCategory::AccessTokenExpired,
            );
        }

        if ($issuedAt > $now + self::CLOCK_SKEW_SECONDS) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token issued-at time is in the future.',
                null,
                OidcFailureCategory::AccessTokenFutureIat,
            );
        }

        if (\array_key_exists('nbf', $data)) {
            $notBefore = $this->numericDate($data['nbf'], 'nbf');

            if ($now < $notBefore - self::CLOCK_SKEW_SECONDS) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'Access token is not yet valid.',
                    null,
                    OidcFailureCategory::AccessTokenNotYetValid,
                );
            }
        }

        return new AccessTokenClaims(
            subject: $data['sub'],
        );
    }

    private function numericDate(mixed $value, string $claim): int
    {
        if (!\is_int($value) && !\is_float($value)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf('Access token %s must be numeric.', $claim),
                null,
                OidcFailureCategory::AccessTokenClaimTypeInvalid,
            );
        }

        return (int) $value;
    }
}
