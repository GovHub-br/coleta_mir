<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Value;

use InvalidArgumentException;

final readonly class HttpResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new InvalidArgumentException('Invalid HTTP status code.');
        }
    }
}
