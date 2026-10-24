<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Protocol;

use JoomlaOidc\Component\OidcClient\Site\Contract\HttpClientInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\ClientConfiguration;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpResponse;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class GovBrProviderClient
{
    private const string CODE_CHALLENGE_METHOD = 'S256';
    private const string RESPONSE_TYPE = 'code';

    public function __construct(
        private readonly ProviderProfileInterface $profile,
        private readonly ClientConfiguration $clientConfig,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function authorizationUrl(
        string $state,
        string $nonce,
        string $codeChallenge,
    ): string {
        $params = [
            'response_type' => self::RESPONSE_TYPE,
            'client_id' => $this->clientConfig->clientId,
            'redirect_uri' => $this->clientConfig->redirectUri,
            'scope' => \implode(' ', $this->profile->mandatoryScopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => self::CODE_CHALLENGE_METHOD,
        ];

        $query = http_build_query($params, '', '&', \PHP_QUERY_RFC3986);

        return $this->profile->authorizationEndpoint() . '?' . $query;
    }

    public function exchangeCode(
        string $code,
        string $codeVerifier,
    ): TokenResponse {
        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->clientConfig->redirectUri,
            'code_verifier' => $codeVerifier,
        ], '', '&', \PHP_QUERY_RFC3986);

        $basicAuth = base64_encode(
            $this->clientConfig->clientId . ':' . $this->clientConfig->clientSecret(),
        );

        $request = new HttpRequest(
            method: 'POST',
            url: $this->profile->tokenEndpoint(),
            headers: [
                'Authorization' => 'Basic ' . $basicAuth,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            body: $body,
        );

        $response = $this->httpClient->request($request);

        if ($response->statusCode !== 200) {
            throw $this->createTokenExchangeFailedException($response);
        }

        $contentTypeValue = self::firstHeaderValue($response, 'content-type');

        if (!str_starts_with($contentTypeValue, 'application/json')) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token endpoint returned non-JSON content type.',
                null,
                OidcFailureCategory::TokenWrongContentType,
            );
        }

        $data = json_decode($response->body, true);

        if (!\is_array($data)) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token endpoint returned invalid JSON.',
                null,
                OidcFailureCategory::TokenInvalidJson,
            );
        }

        if (\array_key_exists('error', $data)) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token endpoint returned an error.',
                null,
                self::resolveOAuthCategory($data['error']),
            );
        }

        if (empty($data['id_token'])) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token response missing required fields.',
                null,
                OidcFailureCategory::TokenMissingIdToken,
            );
        }

        if (empty($data['access_token'])) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token response missing required fields.',
                null,
                OidcFailureCategory::TokenMissingAccessToken,
            );
        }

        if (!\array_key_exists('expires_in', $data)) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token response missing required fields.',
                null,
                OidcFailureCategory::TokenMissingExpiresIn,
            );
        }

        if (!\is_int($data['expires_in']) || $data['expires_in'] <= 0) {
            throw new OidcException(
                OidcErrorCode::TokenExchangeFailed,
                'Token response has invalid expires_in.',
                null,
                OidcFailureCategory::TokenInvalidExpiresIn,
            );
        }

        return new TokenResponse(
            idToken: $data['id_token'],
            accessToken: $data['access_token'],
            expiresIn: $data['expires_in'],
        );
    }

    private function createTokenExchangeFailedException(HttpResponse $response): OidcException
    {
        $category = match (true) {
            $response->statusCode >= 400 && $response->statusCode <= 499 => OidcFailureCategory::TokenHttp4xx,
            $response->statusCode >= 500 && $response->statusCode <= 599 => OidcFailureCategory::TokenHttp5xx,
            default => OidcFailureCategory::TokenHttpOther,
        };

        $contentTypeValue = self::firstHeaderValue($response, 'content-type');

        if (str_starts_with($contentTypeValue, 'application/json')) {
            $data = json_decode($response->body, true);
            if (\is_array($data) && \array_key_exists('error', $data)) {
                $category = self::resolveOAuthCategory($data['error']);
            }
        }

        return new OidcException(
            OidcErrorCode::TokenExchangeFailed,
            \sprintf('Token endpoint returned HTTP %d.', $response->statusCode),
            null,
            $category,
        );
    }

    private static function resolveOAuthCategory(mixed $error): OidcFailureCategory
    {
        if (!\is_string($error) || $error === '') {
            return OidcFailureCategory::TokenOAuthOther;
        }

        return match ($error) {
            'invalid_request' => OidcFailureCategory::TokenOAuthInvalidRequest,
            'invalid_client' => OidcFailureCategory::TokenOAuthInvalidClient,
            'invalid_grant' => OidcFailureCategory::TokenOAuthInvalidGrant,
            'unauthorized_client' => OidcFailureCategory::TokenOAuthUnauthorizedClient,
            'unsupported_grant_type' => OidcFailureCategory::TokenOAuthUnsupportedGrantType,
            'invalid_scope' => OidcFailureCategory::TokenOAuthInvalidScope,
            default => OidcFailureCategory::TokenOAuthOther,
        };
    }

    private static function firstHeaderValue(HttpResponse $response, string $name): string
    {
        foreach ($response->headers as $headerName => $values) {
            if (strtolower($headerName) === strtolower($name)) {
                return $values[0] ?? '';
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $request = new HttpRequest(
            method: 'GET',
            url: $this->profile->userInfoEndpoint(),
            headers: [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        );

        $response = $this->httpClient->request($request);

        if ($response->statusCode !== 200) {
            $category = match (true) {
                $response->statusCode >= 300 && $response->statusCode <= 399 => OidcFailureCategory::UserInfoHttp3xx,
                $response->statusCode >= 400 && $response->statusCode <= 499 => OidcFailureCategory::UserInfoHttp4xx,
                $response->statusCode >= 500 && $response->statusCode <= 599 => OidcFailureCategory::UserInfoHttp5xx,
                default => OidcFailureCategory::UserInfoHttpOther,
            };

            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                \sprintf('UserInfo endpoint returned HTTP %d.', $response->statusCode),
                failureCategory: $category,
            );
        }

        $contentTypeValue = self::firstHeaderValue($response, 'content-type');

        if (!str_starts_with($contentTypeValue, 'application/json')) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'UserInfo endpoint returned non-JSON content type.',
                null,
                OidcFailureCategory::UserInfoWrongContentType,
            );
        }

        $data = json_decode($response->body, true);

        if (!\is_array($data)) {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'UserInfo endpoint returned invalid JSON.',
                null,
                OidcFailureCategory::UserInfoInvalidJson,
            );
        }

        if (!isset($data['sub']) || !\is_string($data['sub']) || $data['sub'] === '') {
            throw new OidcException(
                OidcErrorCode::InvalidToken,
                'UserInfo endpoint returned an invalid subject.',
                null,
                OidcFailureCategory::UserInfoInvalidSubject,
            );
        }

        return $data;
    }
}
