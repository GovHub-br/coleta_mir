<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Identity;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class JoomlaIdentityRepository implements IdentityRepositoryInterface
{
    private const TABLE = '#__oidc_identities';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function findUserId(
        string $providerKey,
        string $issuer,
        string $subject,
    ): ?int {
        $query = $this->db->getQuery(true);
        $query->select($this->quoteName('user_id'))
            ->from($this->quoteName(self::TABLE))
            ->where($this->quoteName('provider_key') . ' = :providerKey')
            ->where($this->quoteName('issuer') . ' = :issuer')
            ->where($this->quoteName('subject') . ' = :subject');

        $query->bind(':providerKey', $providerKey, ParameterType::STRING);
        $query->bind(':issuer', $issuer, ParameterType::STRING);
        $query->bind(':subject', $subject, ParameterType::STRING);

        $this->db->setQuery($query, 0, 1);

        try {
            $userId = $this->db->loadResult();
        } catch (\RuntimeException $e) {
            throw new OidcException(
                OidcErrorCode::InternalError,
                'Identity lookup query failed.',
                $e,
                OidcFailureCategory::IdentityRepositoryFailed,
            );
        }

        if ($userId === null) {
            return null;
        }

        return (int) $userId;
    }

    public function link(
        int $userId,
        string $providerKey,
        string $issuer,
        string $subject,
    ): void {
        $existingIdentity = $this->findUserId($providerKey, $issuer, $subject);

        if ($existingIdentity !== null && $existingIdentity !== $userId) {
            throw new OidcException(
                OidcErrorCode::IdentityConflict,
                'Subject is already linked to another user.',
                null,
                OidcFailureCategory::IdentityConflictExistingSubject,
            );
        }

        $existingByUser = $this->findByProviderUser($providerKey, $userId);

        if (
            $existingByUser !== null
            && ($existingByUser['issuer'] !== $issuer || $existingByUser['subject'] !== $subject)
        ) {
            throw new OidcException(
                OidcErrorCode::IdentityConflict,
                'User already has a different identity link.',
                null,
                OidcFailureCategory::IdentityConflictExistingUser,
            );
        }

        $query = $this->db->getQuery(true);
        $query->insert($this->quoteName(self::TABLE))
            ->columns([
                $this->db->quoteName('provider_key'),
                $this->db->quoteName('issuer'),
                $this->db->quoteName('subject'),
                $this->db->quoteName('user_id'),
            ])
            ->values(':providerKey, :issuer, :subject, :userId');

        $query->bind(':providerKey', $providerKey, ParameterType::STRING);
        $query->bind(':issuer', $issuer, ParameterType::STRING);
        $query->bind(':subject', $subject, ParameterType::STRING);
        $query->bind(':userId', $userId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 1062 || (int) $e->getCode() === 23505) {
                throw new OidcException(
                    OidcErrorCode::IdentityConflict,
                    'Duplicate identity link detected at insert.',
                    $e,
                    OidcFailureCategory::IdentityConflictDuplicate,
                );
            }

            throw new OidcException(
                OidcErrorCode::InternalError,
                'Identity link insert failed.',
                $e,
                OidcFailureCategory::IdentityRepositoryFailed,
            );
        }
    }

    public function recordLogin(int $userId, string $providerKey): void
    {
        $query = $this->db->getQuery(true);
        $query->update($this->quoteName(self::TABLE))
            ->set(
                $this->quoteName('last_login_at') . ' = ' . $query->currentTimestamp(),
            )
            ->where($this->quoteName('user_id') . ' = :userId')
            ->where($this->quoteName('provider_key') . ' = :providerKey');

        $query->bind(':userId', $userId, ParameterType::INTEGER);
        $query->bind(':providerKey', $providerKey, ParameterType::STRING);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\RuntimeException $e) {
            throw new OidcException(
                OidcErrorCode::InternalError,
                'Login timestamp update failed.',
                $e,
                OidcFailureCategory::LoginRecordFailed,
            );
        }
    }

    /**
     * @return array{issuer: string, subject: string}|null
     */
    private function findByProviderUser(string $providerKey, int $userId): ?array
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName(['issuer', 'subject']))
            ->from($this->quoteName(self::TABLE))
            ->where($this->quoteName('provider_key') . ' = :providerKey')
            ->where($this->quoteName('user_id') . ' = :userId');

        $query->bind(':providerKey', $providerKey, ParameterType::STRING);
        $query->bind(':userId', $userId, ParameterType::INTEGER);

        $this->db->setQuery($query, 0, 1);

        try {
            $result = $this->db->loadAssoc();
        } catch (\RuntimeException $e) {
            throw new OidcException(
                OidcErrorCode::InternalError,
                'Identity lookup by user failed.',
                $e,
                OidcFailureCategory::IdentityRepositoryFailed,
            );
        }

        if ($result === null) {
            return null;
        }

        return [
            'issuer' => (string) ($result['issuer'] ?? ''),
            'subject' => (string) ($result['subject'] ?? ''),
        ];
    }

    private function quoteName(string $identifier): string
    {
        $quoted = $this->db->quoteName($identifier);

        if (!\is_string($quoted)) {
            throw new \LogicException('Database returned an invalid quoted identifier.');
        }

        return $quoted;
    }
}
