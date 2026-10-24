<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>
<div class="mod-oidc-login">
    <?php if ($user === null || $user->guest): ?>
        <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-oidc-login">
            <?php echo htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php else: ?>
        <form action="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <button type="submit" class="btn btn-secondary btn-oidc-login">
                <?php echo Text::_('JLOGOUT'); ?>
            </button>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>
