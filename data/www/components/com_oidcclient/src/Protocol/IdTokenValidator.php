<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use DateTimeImmutable;
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

final class IdTokenValidator
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

    public function validate(
        string $idToken,
        JWKSet $jwkset,
        ProviderProfileInterface $profile,
        ClientConfiguration $clientConfig,
        string $nonce,
        ClockInterface $clock,
    ): IdTokenClaims {
        $jws = $this->unserialize($idToken);

        $algorithm = $this->extractAlgorithm($jws, $profile);
        $kid = $this->extractKid($jws);
        $key = $this->selectKey($kid, $algorithm, $jwkset);

        $this->verifySignature($jws, $key);

        $payload = $this->decodePayload($jws);

        $claims = $this->parseClaims($payload);

        $this->validateIssuer($claims, $profile);
        $this->validateAudience($claims, $clientConfig);
        $this->validateAuthorizedParty($claims, $clientConfig);
        $this->validateTiming($claims, $clock);
        $this->validateNonce($claims, $nonce);
        $this->validateSubject($claims);

        return $claims;
    }

    private function unserialize(string $idToken): \Jose\Component\Signature\JWS
    {
        try {
            return $this->serializerManager->unserialize($idToken);
        } catch (\Throwable $e) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Failed to unserialize ID Token.',
                $e,
                failureCategory: OidcFailureCategory::IdTokenMalformed,
            );
        }
    }

    private function extractKid(\Jose\Component\Signature\JWS $jws): string
    {
        $header = $jws->getSignature(0)->getProtectedHeader();
        $kid = $header['kid'] ?? '';

        if (!\is_string($kid) || $kid === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token missing kid in protected header.',
                failureCategory: OidcFailureCategory::IdTokenKidMissing,
            );
        }

        return $kid;
    }

    private function selectKey(string $kid, string $algorithm, JWKSet $jwkset): JWK
    {
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

        if ($matches === []) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf('No JWK found for kid: %s.', $kid),
                failureCategory: OidcFailureCategory::IdTokenSigningKeyNotFound,
            );
        }

        if (\count($matches) > 1) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf('Ambiguous kid: %s.', $kid),
                failureCategory: OidcFailureCategory::IdTokenSigningKeyAmbiguous,
            );
        }

        return $matches[0];
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
                'JWS signature verification threw an exception.',
                $e,
                failureCategory: OidcFailureCategory::IdTokenSignatureInvalid,
            );
        }

        if (!$verified) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token JWS signature verification failed.',
                failureCategory: OidcFailureCategory::IdTokenSignatureInvalid,
            );
        }
    }

    private function decodePayload(\Jose\Component\Signature\JWS $jws): string
    {
        $payload = $jws->getPayload();

        if ($payload === null || $payload === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token has empty payload.',
                failureCategory: OidcFailureCategory::IdTokenMalformed,
            );
        }

        return $payload;
    }

    private function parseClaims(string $payload): IdTokenClaims
    {
        $data = json_decode($payload, true);

        if (!\is_array($data)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token payload is not valid JSON.',
                failureCategory: OidcFailureCategory::IdTokenPayloadInvalidJson,
            );
        }

        $required = ['iss', 'sub', 'aud', 'exp', 'iat', 'nonce'];

        foreach ($required as $field) {
            if (!\array_key_exists($field, $data)) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    \sprintf('ID Token missing required claim: %s.', $field),
                    failureCategory: OidcFailureCategory::IdTokenMissingRequiredClaim,
                );
            }
        }

        $issuer = $data['iss'];
        if (!\is_string($issuer)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token iss must be a string.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $subject = $data['sub'];
        if (!\is_string($subject)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token sub must be a string.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $audience = \is_array($data['aud']) ? $data['aud'] : [$data['aud']];

        if ($audience === []) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token audience is empty.',
                failureCategory: OidcFailureCategory::IdTokenAudienceInvalid,
            );
        }

        foreach ($audience as $a) {
            if (!\is_string($a)) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'ID Token aud element must be a string.',
                    failureCategory: OidcFailureCategory::IdTokenAudienceInvalid,
                );
            }
        }

        $audience = array_values($audience);

        $exp = $data['exp'];
        if (!\is_int($exp) && !\is_float($exp)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token exp must be numeric.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $iat = $data['iat'];
        if (!\is_int($iat) && !\is_float($iat)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token iat must be numeric.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $nonce = $data['nonce'];
        if (!\is_string($nonce)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token nonce must be a string.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $authTimeValue = $data['auth_time'] ?? $iat;
        $this->assertNumericDate($authTimeValue, 'auth_time');

        if (isset($data['nbf'])) {
            $this->assertNumericDate($data['nbf'], 'nbf');
        }

        if (isset($data['azp']) && !\is_string($data['azp'])) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token azp must be a string.',
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }

        $emailVerifiedValue = $data['email_verified'] ?? false;

        if (
            !\is_bool($emailVerifiedValue)
            && !\in_array($emailVerifiedValue, ['true', 'false'], true)
        ) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token email_verified has an invalid boolean representation.',
                failureCategory: OidcFailureCategory::IdTokenEmailVerifiedInvalid,
            );
        }

        $emailVerified = $emailVerifiedValue === true || $emailVerifiedValue === 'true';

        $authenticationMethods = $data['amr'] ?? [];

        if (!\is_array($authenticationMethods)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token amr must be an array.',
                failureCategory: OidcFailureCategory::IdTokenAmrInvalid,
            );
        }

        foreach ($authenticationMethods as $method) {
            if (!\is_string($method) || $method === '') {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'ID Token amr values must be non-empty strings.',
                    failureCategory: OidcFailureCategory::IdTokenAmrInvalid,
                );
            }
        }

        $authenticationMethods = array_values($authenticationMethods);

        $authTime = isset($data['auth_time'])
            ? new DateTimeImmutable('@' . ((int) $authTimeValue))
            : new DateTimeImmutable('@' . ((int) $iat));

        return new IdTokenClaims(
            issuer: $issuer,
            subject: $subject,
            audience: $audience,
            authorizedParty: $data['azp'] ?? null,
            expiresAt: new DateTimeImmutable('@' . ((int) $exp)),
            issuedAt: new DateTimeImmutable('@' . ((int) $iat)),
            authenticatedAt: $authTime,
            notBefore: isset($data['nbf'])
                ? new DateTimeImmutable('@' . ((int) $data['nbf']))
                : null,
            nonce: $nonce,
            displayName: (isset($data['name']) && \is_string($data['name'])) ? $data['name'] : $subject,
            email: (isset($data['email']) && \is_string($data['email'])) ? $data['email'] : null,
            emailVerified: $emailVerified,
            authenticationMethods: $authenticationMethods,
            assuranceLevel: (isset($data['acr']) && \is_string($data['acr'])) ? $data['acr'] : null,
        );
    }

    private function validateAlgorithm(
        \Jose\Component\Signature\JWS $jws,
        ProviderProfileInterface $profile,
    ): void {
        $header = $jws->getSignature(0)->getProtectedHeader();
        $tokenAlg = $header['alg'] ?? '';

        if (!\in_array($tokenAlg, $profile->signingAlgorithms(), true)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf(
                    'ID Token uses unsupported algorithm: %s.',
                    $tokenAlg,
                ),
                failureCategory: OidcFailureCategory::IdTokenAlgorithmInvalid,
            );
        }
    }

    private function extractAlgorithm(
        \Jose\Component\Signature\JWS $jws,
        ProviderProfileInterface $profile,
    ): string {
        $this->validateAlgorithm($jws, $profile);

        return $jws->getSignature(0)->getProtectedHeader()['alg'];
    }

    private function assertNumericDate(mixed $value, string $claim): void
    {
        if (!\is_int($value) && !\is_float($value)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf('ID Token %s must be numeric.', $claim),
                failureCategory: OidcFailureCategory::IdTokenClaimTypeInvalid,
            );
        }
    }

    private function validateIssuer(
        IdTokenClaims $claims,
        ProviderProfileInterface $profile,
    ): void {
        if ($claims->issuer !== $profile->issuer()) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                \sprintf(
                    'ID Token issuer mismatch: expected %s, got %s.',
                    $profile->issuer(),
                    $claims->issuer,
                ),
                failureCategory: OidcFailureCategory::IdTokenIssuerMismatch,
            );
        }
    }

    private function validateAudience(
        IdTokenClaims $claims,
        ClientConfiguration $clientConfig,
    ): void {
        if (!\in_array($clientConfig->clientId, $claims->audience, true)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token audience does not include the configured client ID.',
                failureCategory: OidcFailureCategory::IdTokenAudienceMismatch,
            );
        }
    }

    private function validateAuthorizedParty(
        IdTokenClaims $claims,
        ClientConfiguration $clientConfig,
    ): void {
        if (\count($claims->audience) > 1) {
            if (
                $claims->authorizedParty === null
                || $claims->authorizedParty !== $clientConfig->clientId
            ) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'ID Token with multiple audiences must have azp matching the client ID.',
                    failureCategory: OidcFailureCategory::IdTokenAuthorizedPartyMismatch,
                );
            }
        }
    }

    private function validateTiming(
        IdTokenClaims $claims,
        ClockInterface $clock,
    ): void {
        $now = $clock->now();
        $skew = new \DateInterval('PT' . self::CLOCK_SKEW_SECONDS . 'S');

        $adjustedExp = $claims->expiresAt->add($skew);

        if ($now > $adjustedExp) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token has expired.',
                failureCategory: OidcFailureCategory::IdTokenExpired,
            );
        }

        $adjustedIat = $claims->issuedAt->sub($skew);

        if ($now < $adjustedIat) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token issued in the future.',
                failureCategory: OidcFailureCategory::IdTokenFutureIat,
            );
        }

        $adjustedAuthTime = $claims->authenticatedAt->sub($skew);

        if ($now < $adjustedAuthTime) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token authentication time is in the future.',
                failureCategory: OidcFailureCategory::IdTokenFutureAuthTime,
            );
        }

        if ($claims->notBefore !== null) {
            $adjustedNbf = $claims->notBefore->sub($skew);

            if ($now < $adjustedNbf) {
                throw new OidcException(
                    OidcErrorCode::InvalidToken,
                    'ID Token not yet valid (nbf).',
                    failureCategory: OidcFailureCategory::IdTokenNotYetValid,
                );
            }
        }
    }

    private function validateNonce(
        IdTokenClaims $claims,
        string $expectedNonce,
    ): void {
        if ($claims->nonce !== $expectedNonce) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token nonce mismatch.',
                failureCategory: OidcFailureCategory::IdTokenNonceMismatch,
            );
        }
    }

    private function validateSubject(IdTokenClaims $claims): void
    {
        if ($claims->subject === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'ID Token subject is empty.',
                failureCategory: OidcFailureCategory::IdTokenSubjectInvalid,
            );
        }
    }
}
