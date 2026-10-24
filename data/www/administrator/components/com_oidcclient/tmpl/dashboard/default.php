<?php

declare(strict_types=1);

/**
 * @var \JoomlaOidc\Component\OidcClient\Administrator\View\Dashboard\HtmlView $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$data = $this->dashboardData;
$providerLabel = match ($data['provider']) {
    'govbr_staging' => Text::_('COM_OIDCCLIENT_PROVIDER_GOVBR_STAGING'),
    'govbr_production' => Text::_('COM_OIDCCLIENT_PROVIDER_GOVBR_PRODUCTION'),
    default => '-',
};
$userResolutionLabel = match ($data['params']['user_resolution_policy']) {
    'existing_only' => Text::_('COM_OIDCCLIENT_EXISTING_ONLY'),
    default => (string) $data['params']['user_resolution_policy'],
};
$localLoginPolicyLabel = match ($data['params']['local_login_policy']) {
    'govbr_people_ente_local' => Text::_('COM_OIDCCLIENT_CONFIG_LOCAL_LOGIN_SELECTIVE'),
    default => (string) $data['params']['local_login_policy'],
};
$enabled = (bool) $data['params']['enabled'];
$allowExactUsernameLink = (bool) $data['params']['allow_exact_username_link'];
?>

<div class="container-fluid">
    <?php if (!$data['resolved']): ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_OIDCCLIENT_NOT_CONFIGURED'); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <fieldset class="options-form h-100">
                <legend><?php echo Text::_('COM_OIDCCLIENT_ENV_CONFIG'); ?></legend>

                <p class="text-muted"><?php echo Text::_('COM_OIDCCLIENT_ENV_CONFIG_DESC'); ?></p>

                <table class="table align-middle">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_PROVIDER'); ?></th>
                            <td>
                                <?php if ($data['provider_resolved']): ?>
                                    <strong><?php echo $this->escape($providerLabel); ?></strong>
                                <?php else: ?>
                                    <span class="badge bg-warning" role="status">
                                        <?php echo Text::_('COM_OIDCCLIENT_ENV_UNRESOLVED'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ENV_PUBLIC_BASE_URL'); ?></th>
                            <td>
                                <?php if ($data['client_configuration_resolved']): ?>
                                    <code class="text-break"><?php echo $this->escape($data['public_base_url']); ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning" role="status">
                                        <?php echo Text::_('COM_OIDCCLIENT_ENV_UNRESOLVED'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ENV_CLIENT_ID'); ?></th>
                            <td>
                                <span class="badge bg-<?php echo $data['client_configuration_resolved'] ? 'success' : 'warning'; ?>"
                                    role="status">
                                    <?php echo Text::_($data['client_configuration_resolved']
                                        ? 'COM_OIDCCLIENT_ENV_RESOLVED'
                                        : 'COM_OIDCCLIENT_ENV_UNRESOLVED'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ENV_CLIENT_SECRET'); ?></th>
                            <td>
                                <span class="badge bg-<?php echo $data['client_configuration_resolved'] ? 'success' : 'warning'; ?>"
                                    role="status">
                                    <?php echo Text::_($data['client_configuration_resolved']
                                        ? 'COM_OIDCCLIENT_ENV_RESOLVED'
                                        : 'COM_OIDCCLIENT_ENV_UNRESOLVED'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_CALLBACK_URI'); ?></th>
                            <td>
                                <code class="text-break d-block"><?php echo $this->escape($data['callback_uri'] ?: '-'); ?></code>
                                <small class="text-muted"><?php echo Text::_('COM_OIDCCLIENT_ENV_DERIVED_URI'); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_LOGOUT_URI'); ?></th>
                            <td>
                                <code class="text-break d-block"><?php echo $this->escape($data['logout_uri'] ?: '-'); ?></code>
                                <small class="text-muted"><?php echo Text::_('COM_OIDCCLIENT_ENV_DERIVED_URI'); ?></small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>

        <div class="col-lg-6">
            <fieldset class="options-form h-100">
                <legend><?php echo Text::_('COM_OIDCCLIENT_COMPONENT_PARAMS'); ?></legend>

                <p class="text-muted"><?php echo Text::_('COM_OIDCCLIENT_COMPONENT_PARAMS_DESC'); ?></p>

                <table class="table align-middle">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ENABLED'); ?></th>
                            <td>
                                <span class="badge bg-<?php echo $enabled ? 'success' : 'secondary'; ?>" role="status">
                                    <?php echo Text::_($enabled ? 'JYES' : 'JNO'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_USER_RESOLUTION_POLICY'); ?></th>
                            <td><?php echo $this->escape($userResolutionLabel); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ALLOW_EXACT_USERNAME_LINK'); ?></th>
                            <td>
                                <span class="badge bg-<?php echo $allowExactUsernameLink ? 'success' : 'secondary'; ?>" role="status">
                                    <?php echo Text::_($allowExactUsernameLink ? 'JYES' : 'JNO'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_LOCAL_LOGIN_POLICY'); ?></th>
                            <td><?php echo $this->escape($localLoginPolicyLabel); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_OIDCCLIENT_ENTE_GROUP'); ?></th>
                            <td><?php echo (int) $data['params']['ente_group_id']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>
    </div>
</div>
