<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

final class IdentitylinksModel extends ListModel
{
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('id'),
            $db->quoteName('provider_key'),
            $db->quoteName('issuer'),
            $db->quoteName('subject'),
            $db->quoteName('user_id'),
            $db->quoteName('created_at'),
            $db->quoteName('last_login_at'),
        ])
            ->from($this->quoteName($db, '#__oidc_identities'))
            ->order($this->quoteName($db, 'created_at') . ' DESC');

        return $query;
    }

    private function quoteName(DatabaseInterface $db, string $identifier): string
    {
        $quoted = $db->quoteName($identifier);

        if (!\is_string($quoted)) {
            throw new \LogicException('Database returned an invalid quoted identifier.');
        }

        return $quoted;
    }
}
