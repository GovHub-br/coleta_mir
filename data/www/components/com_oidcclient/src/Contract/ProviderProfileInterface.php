<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

interface ProviderProfileInterface
{
    public function key(): string;

    public function issuer(): string;

    public function authorizationEndpoint(): string;

    public function tokenEndpoint(): string;

    public function userInfoEndpoint(): string;

    public function jwksEndpoint(): string;

    public function logoutEndpoint(): string;

    /** @return list<string> */
    public function mandatoryScopes(): array;

    /** @return list<string> */
    public function signingAlgorithms(): array;
}
