<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use Joomla\Http\Exception\InvalidResponseCodeException;
use Joomla\Http\HttpFactory;
use Joomla\Uri\Uri;
use JoomlaOidc\Component\OidcClient\Site\Contract\HttpClientInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpResponse;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class JoomlaHttpClient implements HttpClientInterface
{
    private const int HARD_MAX_BYTES = 1_048_576;

    public function __construct(
        private readonly ProviderProfileInterface $profile,
        private readonly HttpFactory $httpFactory,
    ) {
    }

    public function request(HttpRequest $request): HttpResponse
    {
        $this->assertEndpointAllowed($request->url);

        $endpoint = $this->resolveEndpointType($request->url);

        $maximumBytes = min($request->maxResponseBytes, self::HARD_MAX_BYTES);
        $responseTooLarge = false;

        $options = [
            'follow_location' => false,
            'transport.curl' => [
                CURLOPT_CONNECTTIMEOUT => $request->connectTimeoutSeconds,
                CURLOPT_TIMEOUT => $request->totalTimeoutSeconds,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_XFERINFOFUNCTION => static function (
                    mixed $curlHandle,
                    float $downloadTotal,
                    float $downloadedBytes,
                    float $uploadTotal,
                    float $uploadedBytes,
                ) use (
                    $maximumBytes,
                    &$responseTooLarge,
                ): int {
                    unset($curlHandle, $downloadTotal, $uploadTotal, $uploadedBytes);

                    if ($downloadedBytes <= $maximumBytes) {
                        return 0;
                    }

                    $responseTooLarge = true;

                    return 1;
                },
            ],
        ];

        try {
            $http = $this->httpFactory->getHttp($options, ['curl']);

            if ($request->method === 'GET') {
                $response = $http->get($request->url, $request->headers, $request->totalTimeoutSeconds);
            } else {
                $response = $http->post(
                    $request->url,
                    $request->body,
                    $request->headers,
                    $request->totalTimeoutSeconds,
                );
            }
        } catch (InvalidResponseCodeException $e) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'Provider returned an invalid HTTP response.',
                $e,
                $this->transportCategory($endpoint),
            );
        } catch (\RuntimeException $e) {
            if ($responseTooLarge) {
                throw new OidcException(
                    OidcErrorCode::HttpRequestFailed,
                    'Provider response exceeds the configured maximum size.',
                    $e,
                    $this->responseTooLargeCategory($endpoint),
                );
            }

            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'HTTP request to provider failed.',
                $e,
                $this->transportCategory($endpoint),
            );
        }

        if ($responseTooLarge) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'Provider response exceeds the configured maximum size.',
                failureCategory: $this->responseTooLargeCategory($endpoint),
            );
        }

        $contentLength = $response->getHeaderLine('Content-Length');

        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $maximumBytes) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'Provider response exceeds the configured maximum size.',
                failureCategory: $this->responseTooLargeCategory($endpoint),
            );
        }

        $body = (string) $response->getBody();
        $bodyLength = \strlen($body);

        if ($bodyLength > $maximumBytes) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                \sprintf(
                    'Provider response exceeds maximum size (%d > %d bytes).',
                    $bodyLength,
                    $maximumBytes,
                ),
                failureCategory: $this->responseTooLargeCategory($endpoint),
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            return new HttpResponse(
                $statusCode,
                $response->getHeaders(),
                $body,
            );
        }

        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'), 2)[0]));

        if (!\in_array($contentType, ['application/json', 'application/jwk-set+json'], true)) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'Provider endpoint returned an unsupported content type.',
                failureCategory: $this->wrongContentTypeCategory($endpoint),
            );
        }

        return new HttpResponse(
            $statusCode,
            $response->getHeaders(),
            $body,
        );
    }

    private function assertEndpointAllowed(string $url): void
    {
        $allowedEndpoints = [
            $this->profile->tokenEndpoint(),
            $this->profile->userInfoEndpoint(),
            $this->profile->jwksEndpoint(),
        ];

        $uri = new Uri($url);
        $scheme = $uri->getScheme();

        if ($scheme !== 'https') {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'Only HTTPS requests are allowed.',
                failureCategory: OidcFailureCategory::HttpEndpointRejected,
            );
        }

        if (!\in_array($url, $allowedEndpoints, true)) {
            throw new OidcException(
                OidcErrorCode::HttpRequestFailed,
                'URL is not in the provider endpoint allowlist.',
                failureCategory: OidcFailureCategory::HttpEndpointRejected,
            );
        }
    }

    private function resolveEndpointType(string $url): string
    {
        if ($url === $this->profile->tokenEndpoint()) {
            return 'token';
        }

        if ($url === $this->profile->userInfoEndpoint()) {
            return 'userinfo';
        }

        return 'jwks';
    }

    private function transportCategory(string $endpoint): OidcFailureCategory
    {
        return match ($endpoint) {
            'token' => OidcFailureCategory::TokenHttpTransport,
            'userinfo' => OidcFailureCategory::UserInfoHttpTransport,
            default => OidcFailureCategory::JwksHttpTransport,
        };
    }

    private function responseTooLargeCategory(string $endpoint): OidcFailureCategory
    {
        return match ($endpoint) {
            'token' => OidcFailureCategory::TokenResponseTooLarge,
            'userinfo' => OidcFailureCategory::UserInfoResponseTooLarge,
            default => OidcFailureCategory::JwksResponseTooLarge,
        };
    }

    private function wrongContentTypeCategory(string $endpoint): OidcFailureCategory
    {
        return match ($endpoint) {
            'token' => OidcFailureCategory::TokenWrongContentType,
            'userinfo' => OidcFailureCategory::UserInfoWrongContentType,
            default => OidcFailureCategory::JwksWrongContentType,
        };
    }
}
