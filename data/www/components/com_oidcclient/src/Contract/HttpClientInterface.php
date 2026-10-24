<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\HttpResponse;

interface HttpClientInterface
{
    /**
     * Implementations must verify TLS, reject redirects, enforce request
     * timeouts and response size, and return the response without interpreting
     * provider-specific JSON.
     *
     * @throws OidcException With HttpRequestFailed.
     */
    public function request(HttpRequest $request): HttpResponse;
}
