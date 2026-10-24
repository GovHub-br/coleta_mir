<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\EnvProviderProfileResolver;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProfileRegistry;

final class IdentitylinkModel extends AdminModel
{
    /**
     * @param array<string, mixed> $options
     */
    public function getTable($name = 'Identity', $prefix = 'Table', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * @param array<string, mixed> $data
     * @param string               $key
     */
    protected function allowEdit($data = [], $key = 'id'): bool
    {
        return Factory::getApplication()->getIdentity()?->authorise('core.admin', 'com_oidcclient') ?? false;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function allowNew($data = []): bool
    {
        return Factory::getApplication()->getIdentity()?->authorise('core.admin', 'com_oidcclient') ?? false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function getForm($data = [], $loadData = true): \Joomla\CMS\Form\Form|false
    {
        $form = $this->loadForm(
            'com_oidcclient.identitylink',
            'identity_link',
            ['control' => 'jform', 'load_data' => $loadData],
        );

        if ($form === null) {
            return false;
        }

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadFormData(): array
    {
        $app = Factory::getApplication();

        if (!$app instanceof AdministratorApplication) {
            return [];
        }

        $data = $app->getUserState('com_oidcclient.edit.identitylink.data', []);

        if ($data !== []) {
            return $data;
        }

        $item = $this->getItem();
        $data = $item ? (array) $item : [];

        if ((int) ($data['id'] ?? 0) !== 0) {
            return $data;
        }

        try {
            $profile = (new EnvProviderProfileResolver(new GovBrProfileRegistry()))->resolve();
            $data['provider_key'] = $profile->key();
            $data['issuer'] = $profile->issuer();
        } catch (OidcException) {
            // The required fields remain empty so Joomla blocks an unconfigured link.
        }

        return $data;
    }
}
