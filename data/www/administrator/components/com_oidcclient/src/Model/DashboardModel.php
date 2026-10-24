<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Component\ComponentHelper;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\EnvConfigurationResolver;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\EnvProviderProfileResolver;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProfileRegistry;

final class DashboardModel extends BaseModel
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $clientConfiguration = null;
        $providerProfile = null;

        try {
            $clientConfiguration = (new EnvConfigurationResolver())->resolve();
        } catch (OidcException) {
        }

        try {
            $providerProfile = (new EnvProviderProfileResolver(new GovBrProfileRegistry()))->resolve();
        } catch (OidcException) {
        }

        $clientConfigurationResolved = $clientConfiguration !== null;
        $providerResolved = $providerProfile !== null;
        $resolved = $clientConfigurationResolved && $providerResolved;
        $publicBaseUrl = '';
        $callbackUri = '';
        $logoutUri = '';

        if ($clientConfiguration !== null) {
            $redirectUriParts = parse_url($clientConfiguration->redirectUri);

            if (\is_array($redirectUriParts) && isset($redirectUriParts['host'])) {
                $publicBaseUrl = 'https://' . $redirectUriParts['host'];

                if (isset($redirectUriParts['port'])) {
                    $publicBaseUrl .= ':' . $redirectUriParts['port'];
                }
            }

            $callbackUri = $clientConfiguration->redirectUri;
            $logoutUri = $clientConfiguration->postLogoutRedirectUri;
        }

        $componentParams = ComponentHelper::getParams('com_oidcclient');

        return [
            'resolved'      => $resolved,
            'client_configuration_resolved' => $clientConfigurationResolved,
            'provider_resolved' => $providerResolved,
            'provider'      => $providerProfile?->key() ?? '',
            'public_base_url' => $publicBaseUrl,
            'callback_uri'  => $callbackUri,
            'logout_uri'    => $logoutUri,
            'params'        => [
                'enabled'                     => $componentParams->get('enabled', 0),
                'user_resolution_policy'      => $componentParams->get('user_resolution_policy', 'existing_only'),
                'allow_exact_username_link'   => $componentParams->get('allow_exact_username_link', 0),
                'local_login_policy'          => $componentParams->get(
                    'local_login_policy',
                    'govbr_people_ente_local',
                ),
                'ente_group_id'               => $componentParams->get('ente_group_id', 11),
            ],
        ];
    }
}
