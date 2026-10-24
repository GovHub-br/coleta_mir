<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public int $maxResponseBytes = 1_048_576,
        public int $connectTimeoutSeconds = 3,
        public int $totalTimeoutSeconds = 10,
    ) {
        if (!\in_array($this->method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Unsupported HTTP method.');
        }

        $parts = parse_url($this->url);

        if (
            filter_var($this->url, FILTER_VALIDATE_URL) === false
            || !\is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException(
                'HTTP URL must be an HTTPS URL without credentials.',
            );
        }

        if ($this->maxResponseBytes <= 0) {
            throw new InvalidArgumentException(
                'Maximum response size must be positive.',
            );
        }

        if (
            $this->connectTimeoutSeconds <= 0
            || $this->totalTimeoutSeconds < $this->connectTimeoutSeconds
        ) {
            throw new InvalidArgumentException('Invalid HTTP timeout values.');
        }
    }
}
