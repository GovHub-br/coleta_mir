<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Contract;

use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LoginTransaction;

interface LoginTransactionStoreInterface
{
    /**
     * Stores at most five active transactions. At capacity, the oldest entry
     * is evicted before the new transaction is stored.
     *
     * @throws OidcException With InvalidRequest when state cannot be stored.
     */
    public function store(string $rawState, LoginTransaction $transaction): void;

    /**
     * @throws OidcException With InvalidState when state is unknown or reused,
     *                       or ExpiredTransaction when it has expired.
     */
    public function consume(string $rawState): LoginTransaction;
}
