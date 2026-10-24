<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Identity;

use Joomla\CMS\User\UserFactoryInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;
use JoomlaOidc\Component\OidcClient\Site\Value\ValidatedPrincipal;

final class JoomlaUserResolver implements UserResolverInterface
{
    public function __construct(
        private readonly IdentityRepositoryInterface $identityRepo,
        private readonly UserFactoryInterface $userFactory,
        private readonly bool $allowExactUsernameLink = false,
    ) {
    }

    public function resolve(ValidatedPrincipal $principal): LocalUser
    {
        $userId = $this->identityRepo->findUserId(
            $principal->providerKey,
            $principal->issuer,
            $principal->subject,
        );

        if ($userId === null) {
            if (!$this->allowExactUsernameLink) {
                throw new OidcException(
                    OidcErrorCode::IdentityNotLinked,
                    'No identity link found.',
                    null,
                    OidcFailureCategory::IdentityLinkMissing,
                );
            }

            return $this->resolveByUsername($principal);
        }

        $user = $this->loadUser($userId);
        $this->assertUserAllowed($user);

        return new LocalUser(
            (int) $user->get('id'),
            $user->get('username'),
            $user->get('name'),
            $user->get('email'),
        );
    }

    private function resolveByUsername(ValidatedPrincipal $principal): LocalUser
    {
        $username = $principal->subject;
        $user = $this->userFactory->loadUserByUsername($username);

        if ((int) $user->get('id') <= 0) {
            throw new OidcException(
                OidcErrorCode::IdentityNotLinked,
                'No matching username found.',
                null,
                OidcFailureCategory::IdentityUsernameMatchMissing,
            );
        }

        $this->assertUserAllowed($user);

        $this->identityRepo->link(
            (int) $user->get('id'),
            $principal->providerKey,
            $principal->issuer,
            $principal->subject,
        );

        return new LocalUser(
            (int) $user->get('id'),
            (string) $user->get('username'),
            (string) $user->get('name'),
            (string) $user->get('email'),
        );
    }

    private function assertUserAllowed(\Joomla\CMS\User\User $user): void
    {
        if ((int) $user->get('block') === 1) {
            throw new OidcException(
                OidcErrorCode::UserNotAllowed,
                'User account is blocked.',
                null,
                OidcFailureCategory::IdentityUserBlocked,
            );
        }

        $activation = $user->get('activation');

        if ($activation !== '' && $activation !== null && $activation !== '0') {
            throw new OidcException(
                OidcErrorCode::UserNotAllowed,
                'User account is not activated.',
                null,
                OidcFailureCategory::IdentityUserNotActivated,
            );
        }
    }

    private function loadUser(int $userId): \Joomla\CMS\User\User
    {
        $user = $this->userFactory->loadUserById($userId);

        if ((int) $user->get('id') <= 0) {
            throw new OidcException(
                OidcErrorCode::UserNotAllowed,
                'Linked user account not found.',
                null,
                OidcFailureCategory::IdentityUserMissing,
            );
        }

        return $user;
    }
}
