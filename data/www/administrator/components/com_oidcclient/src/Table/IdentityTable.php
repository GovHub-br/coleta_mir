<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProfileRegistry;
use JoomlaOidc\Component\OidcClient\Site\Value\Cpf;

final class IdentityTable extends Table
{
    public function __construct(private readonly DatabaseDriver $database)
    {
        parent::__construct('#__oidc_identities', 'id', $this->database);
    }

    /**
     * @return list<string>
     */
    protected function getAvailableFields(): array
    {
        return [
            'id',
            'provider_key',
            'issuer',
            'subject',
            'user_id',
            'created_at',
            'last_login_at',
        ];
    }

    public function check(): bool
    {
        try {
            $profile = (new GovBrProfileRegistry())->get((string) $this->provider_key);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException('Provider key is not a supported GOV.BR profile.', 0, $exception);
        }

        if ($this->issuer !== $profile->issuer()) {
            throw new \RuntimeException('Issuer does not match the pinned provider profile.');
        }

        try {
            new Cpf((string) $this->subject);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException('Subject must be a valid normalized CPF.', 0, $exception);
        }

        if ($this->user_id === null || $this->user_id <= 0) {
            throw new \RuntimeException('User ID is required and must be positive.');
        }

        $userId = (int) $this->user_id;
        $query = $this->database->getQuery(true)
            ->select($this->quoteName('id'))
            ->from($this->quoteName('#__users'))
            ->where($this->quoteName('id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER);
        $this->database->setQuery($query, 0, 1);

        try {
            $existingUserId = $this->database->loadResult();
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException('Could not validate the linked Joomla user.', 0, $exception);
        }

        if ((int) $existingUserId !== $userId) {
            throw new \RuntimeException('Linked Joomla user does not exist.');
        }

        return true;
    }

    public function store($updateNulls = false): bool
    {
        $this->check();

        if ($this->id === 0 || $this->id === null) {
            $this->created_at = Factory::getDate()->toSql();
        }

        $this->last_login_at = $this->last_login_at ?? null;

        try {
            return parent::store($updateNulls);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 1062 || $e->getCode() === 23505) {
                throw new \RuntimeException(
                    'An identity link with the same issuer+subject or provider+user already exists.',
                    409,
                    $e,
                );
            }

            throw $e;
        }
    }

    private function quoteName(string $identifier): string
    {
        $quoted = $this->database->quoteName($identifier);

        if (!\is_string($quoted)) {
            throw new \LogicException('Database returned an invalid quoted identifier.');
        }

        return $quoted;
    }
}
