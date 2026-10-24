<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Infrastructure;

use Joomla\Session\SessionInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class SessionLoginGrantStore implements LoginGrantStoreInterface
{
    private const SESSION_KEY = 'com_oidcclient.login_grants';
    private const GRANT_BYTES = 32;
    private const TTL_SECONDS = 60;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly ClockInterface $clock,
    ) {
    }

    public function issue(LocalUser $user): string
    {
        $rawGrant = bin2hex(random_bytes(self::GRANT_BYTES));
        $hash = $this->hashGrant($rawGrant);

        $grants = $this->load();
        $now = $this->clock->now();
        $expiresAt = $now->getTimestamp() + self::TTL_SECONDS;

        $grants[$hash] = [
            'user_id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->displayName,
            'email' => $user->email,
            'expires_at' => $expiresAt,
        ];

        $this->save($grants);

        return $rawGrant;
    }

    public function consume(string $rawGrant): LocalUser
    {
        $hash = $this->hashGrant($rawGrant);
        $grants = $this->load();

        $entry = $grants[$hash] ?? null;

        if ($entry === null) {
            throw new OidcException(
                OidcErrorCode::InvalidGrant,
                'Login grant not found.',
                failureCategory: OidcFailureCategory::LoginGrantInvalid,
            );
        }

        unset($grants[$hash]);
        $this->save($grants);

        $now = $this->clock->now()->getTimestamp();

        if ($entry['expires_at'] < $now) {
            throw new OidcException(
                OidcErrorCode::ExpiredGrant,
                'Login grant has expired.',
                failureCategory: OidcFailureCategory::LoginGrantExpired,
            );
        }

        return new LocalUser(
            $entry['user_id'],
            $entry['username'],
            $entry['display_name'],
            $entry['email'],
        );
    }

    private function hashGrant(string $rawGrant): string
    {
        return hash('sha256', $rawGrant);
    }

    /** @return array<string, array{
     *     user_id: int,
     *     username: string,
     *     display_name: string,
     *     email: string|null,
     *     expires_at: int
     * }>
     */
    private function load(): array
    {
        $data = $this->session->get(self::SESSION_KEY);

        if (!\is_array($data)) {
            return [];
        }

        return $data;
    }

    /** @param array<string, array{
     *     user_id: int,
     *     username: string,
     *     display_name: string,
     *     email: string|null,
     *     expires_at: int
     * }> $grants
     */
    private function save(array $grants): void
    {
        $this->session->set(self::SESSION_KEY, $grants);
    }
}
