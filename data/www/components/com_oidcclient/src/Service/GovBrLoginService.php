<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Service;

use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginTransactionStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Identity\IdentityRepositoryInterface;
use JoomlaOidc\Component\OidcClient\Site\Identity\UserResolverInterface;
use JoomlaOidc\Component\OidcClient\Site\Protocol\AccessTokenValidator;
use JoomlaOidc\Component\OidcClient\Site\Protocol\CryptoRandom;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProviderClient;
use JoomlaOidc\Component\OidcClient\Site\Protocol\IdTokenClaims;
use JoomlaOidc\Component\OidcClient\Site\Protocol\IdTokenValidator;
use JoomlaOidc\Component\OidcClient\Site\Protocol\JwksLoader;
use JoomlaOidc\Component\OidcClient\Site\Protocol\TokenResponse;
use JoomlaOidc\Component\OidcClient\Site\Value\AssuranceLevel;
use JoomlaOidc\Component\OidcClient\Site\Value\AuthorizationRedirect;
use JoomlaOidc\Component\OidcClient\Site\Value\CallbackRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\ClientConfiguration;
use JoomlaOidc\Component\OidcClient\Site\Value\Cpf;
use JoomlaOidc\Component\OidcClient\Site\Value\LoginStartRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\LoginTransaction;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;
use JoomlaOidc\Component\OidcClient\Site\Value\ValidatedPrincipal;

final class GovBrLoginService
{
    private const int TRANSACTION_TTL_SECONDS = 300;

    public function __construct(
        private readonly ProviderProfileInterface $profile,
        private readonly ClientConfiguration $clientConfig,
        private readonly LoginTransactionStoreInterface $transactionStore,
        private readonly ClockInterface $clock,
        private readonly CryptoRandom $cryptoRandom,
        private readonly GovBrProviderClient $providerClient,
        private readonly JwksLoader $jwksLoader,
        private readonly IdTokenValidator $idTokenValidator,
        private readonly AccessTokenValidator $accessTokenValidator,
        private readonly UserResolverInterface $userResolver,
        private readonly LoginGrantStoreInterface $loginGrantStore,
        private readonly IdentityRepositoryInterface $identityRepository,
        private readonly bool $enabled,
    ) {
    }

    public function begin(LoginStartRequest $request): AuthorizationRedirect
    {
        $this->assertEnabled();

        $state = $this->cryptoRandom->generateState();
        $nonce = $this->cryptoRandom->generateNonce();
        $pkceVerifier = $this->cryptoRandom->generatePkceVerifier();
        $codeChallenge = $this->cryptoRandom->derivePkceChallengeS256($pkceVerifier);
        $now = $this->clock->now();

        $this->transactionStore->store(
            $state,
            new LoginTransaction(
                providerKey: $this->profile->key(),
                nonce: $nonce,
                pkceVerifier: $pkceVerifier,
                returnUrl: $request->returnUrl,
                createdAt: $now,
                expiresAt: $now->add(new \DateInterval('PT' . self::TRANSACTION_TTL_SECONDS . 'S')),
            ),
        );

        return new AuthorizationRedirect(
            $this->providerClient->authorizationUrl($state, $nonce, $codeChallenge),
        );
    }

    public function complete(CallbackRequest $request): ResolvedLogin
    {
        $transaction = $this->transactionStore->consume($request->state);
        $this->assertEnabled();

        if ($request->error !== null) {
            throw new OidcException(
                OidcErrorCode::ProviderError,
                \sprintf('Provider returned error: %s.', $request->error),
                failureCategory: OidcFailureCategory::ProviderCallbackError,
            );
        }

        if ($request->code === null) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Authorization code is missing.',
                failureCategory: OidcFailureCategory::MissingAuthorizationCode,
            );
        }

        $tokenResponse = $this->providerClient->exchangeCode(
            $request->code,
            $transaction->pkceVerifier,
        );
        $principal = $this->validatePrincipal($tokenResponse, $transaction);

        return new ResolvedLogin(
            $principal,
            $this->userResolver->resolve($principal),
            $transaction->returnUrl,
        );
    }

    public function issueGrant(ResolvedLogin $login): string
    {
        return $this->loginGrantStore->issue($login->localUser);
    }

    public function recordLogin(ResolvedLogin $login): void
    {
        $this->identityRepository->recordLogin(
            $login->localUser->id,
            $login->principal->providerKey,
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function providerKey(): string
    {
        return $this->profile->key();
    }

    public function redirectUrl(bool $federated): string
    {
        if (!$federated) {
            return $this->clientConfig->postLogoutRedirectUri;
        }

        return $this->profile->logoutEndpoint()
            . '?post_logout_redirect_uri='
            . rawurlencode($this->clientConfig->postLogoutRedirectUri);
    }

    private function validatePrincipal(
        TokenResponse $tokenResponse,
        LoginTransaction $transaction,
    ): ValidatedPrincipal {
        $jwkset = $this->jwksLoader->load(
            $this->profile,
            $this->peekKid($tokenResponse->idToken),
        );
        $idTokenClaims = $this->idTokenValidator->validate(
            $tokenResponse->idToken,
            $jwkset,
            $this->profile,
            $this->clientConfig,
            $transaction->nonce,
            $this->clock,
        );
        $accessTokenClaims = $this->accessTokenValidator->validateIfJwt(
            $tokenResponse->accessToken,
            $jwkset,
            $this->profile,
            $this->clientConfig,
            $this->clock,
        );

        if ($accessTokenClaims !== null && $accessTokenClaims->subject !== $idTokenClaims->subject) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'Access token subject does not match ID Token subject.',
                failureCategory: OidcFailureCategory::AccessTokenSubjectMismatch,
            );
        }

        $userInfo = $this->providerClient->fetchUserInfo($tokenResponse->accessToken);

        if ($userInfo['sub'] !== $idTokenClaims->subject) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'UserInfo sub does not match ID Token subject.',
                failureCategory: OidcFailureCategory::UserInfoSubjectMismatch,
            );
        }

        return $this->createPrincipal($idTokenClaims);
    }

    private function createPrincipal(IdTokenClaims $claims): ValidatedPrincipal
    {
        try {
            $cpf = new Cpf($claims->subject);
        } catch (\InvalidArgumentException $exception) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'GOV.BR subject is not a valid normalized CPF.',
                $exception,
                failureCategory: OidcFailureCategory::SubjectInvalidCpf,
            );
        }

        $verifiedEmail = $claims->emailVerified && $claims->email !== null
            ? $claims->email
            : null;
        $assuranceLevel = $claims->assuranceLevel !== null
            ? new AssuranceLevel($claims->assuranceLevel)
            : null;

        return new ValidatedPrincipal(
            providerKey: $this->profile->key(),
            issuer: $claims->issuer,
            subject: $cpf->value,
            displayName: $claims->displayName,
            verifiedEmail: $verifiedEmail,
            authenticatedAt: $claims->authenticatedAt,
            authenticationMethods: array_values($claims->authenticationMethods),
            assuranceLevel: $assuranceLevel,
        );
    }

    private function peekKid(string $jwt): ?string
    {
        $firstDot = strpos($jwt, '.');

        if ($firstDot === false || $firstDot === 0) {
            return null;
        }

        $headerB64 = substr($jwt, 0, $firstDot);
        $decoded = base64_decode(strtr($headerB64, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $header = json_decode($decoded, true);

        if (!\is_array($header)) {
            return null;
        }

        $kid = $header['kid'] ?? null;

        return \is_string($kid) && $kid !== '' ? $kid : null;
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new OidcException(
                OidcErrorCode::ConfigurationError,
                'OIDC login is disabled.',
            );
        }
    }
}
