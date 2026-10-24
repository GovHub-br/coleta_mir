<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use Joomla\Session\SessionInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginTransactionStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LoginTransaction;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final class SessionLoginTransactionStore implements LoginTransactionStoreInterface
{
    private const SESSION_KEY = 'com_oidcclient.transactions';
    private const MAX_TRANSACTIONS = 5;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly ClockInterface $clock,
    ) {
    }

    public function store(string $rawState, LoginTransaction $transaction): void
    {
        $digest = $this->hashState($rawState);
        $transactions = $this->load();

        if (\count($transactions) >= self::MAX_TRANSACTIONS) {
            $this->evictOldest($transactions);
        }

        $transactions[$digest] = $transaction;
        $this->save($transactions);
    }

    public function consume(string $rawState): LoginTransaction
    {
        $digest = $this->hashState($rawState);
        $transactions = $this->load();

        $found = null;
        $foundDigest = null;

        foreach ($transactions as $storedDigest => $transaction) {
            if (hash_equals($storedDigest, $digest)) {
                $found = $transaction;
                $foundDigest = $storedDigest;
                break;
            }
        }

        if ($found === null) {
            throw new OidcException(
                OidcErrorCode::InvalidState,
                'State not found in session transactions.',
            );
        }

        unset($transactions[$foundDigest]);
        $this->save($transactions);

        $now = $this->clock->now();

        if ($found->expiresAt < $now) {
            throw new OidcException(
                OidcErrorCode::ExpiredTransaction,
                'Login transaction has expired.',
            );
        }

        return $found;
    }

    private function hashState(string $rawState): string
    {
        return hash('sha256', $rawState);
    }

    /**
     * @param array<string, LoginTransaction> $transactions
     */
    private function evictOldest(array &$transactions): void
    {
        $oldestKey = null;
        $oldestTime = null;

        foreach ($transactions as $key => $transaction) {
            if ($oldestTime === null || $transaction->createdAt < $oldestTime) {
                $oldestTime = $transaction->createdAt;
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset($transactions[$oldestKey]);
        }
    }

    /**
     * @return array<string, LoginTransaction>
     */
    private function load(): array
    {
        $data = $this->session->get(self::SESSION_KEY);

        if (!\is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @param array<string, LoginTransaction> $transactions
     */
    private function save(array $transactions): void
    {
        $this->session->set(self::SESSION_KEY, $transactions);
    }
}
