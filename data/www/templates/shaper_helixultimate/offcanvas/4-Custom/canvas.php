<?php
/**
 * @package Helix_Ultimate_Framework
 * @author JoomShaper <support@joomshaper.com>
 * @copyright Copyright (c) 2010 - 2025 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or Later
 */

defined ('_JEXEC') or die('Restricted Access');

use HelixUltimate\Framework\Platform\Helper;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$params = $displayData->params;
$template = $displayData->template->template;

include_once JPATH_THEMES . '/' . $template . '/features/menu.php';
include_once JPATH_THEMES . '/' . $template . '/features/social.php';
include_once JPATH_THEMES . '/' . $template . '/features/contact.php';
include_once JPATH_THEMES . '/' . $template . '/features/logo.php';

$menu = new HelixUltimateFeatureMenu($params);
$social = new HelixUltimateFeatureSocial($params);
$contact = new HelixUltimateFeatureContact($params);
$logo = new HelixUltimateFeatureLogo($params);

$hasModMenu = array_search('mod_menu', array_column(ModuleHelper::getModules('offcanvas'), 'module'));

$menuType = $params->get('offcanvas_menu', 'mainmenu', 'STRING');
$maxLevel = $params->get('offcanvas_max_level', 0, 'INT');

// Arrow position param
$arrowPos   = $params->get('offcanvas_arrow_position', 'right');
$arrowClass = $arrowPos === 'left' ? 'offcanvas-arrow-left' : '';

$menuModule = Helper::createModule('mod_menu', [
	'title' => 'Main Menu',
	'params' => '{"menutype":"' . $menuType . '","base":"","startLevel":1,"endLevel":' . $maxLevel . ',"showAllChildren":1,"tag_id":"","class_sfx":" nav-pills","window_open":"","layout":"_:default","moduleclass_sfx":"","cache":"1","cache_time":"900","cachemode":"itemid","module_tag":"div","bootstrap_size":"0","header_tag":"h3","header_class":"","style":"0", "hu_offcanvas": 1}',
	'name' => 'menu'
]);

$searchModule = Helper::getSearchModule('-canvas');
?>
<div class="offcanvas-menu border-menu center-alignment text-center <?php echo $arrowClass; ?>" tabindex="-1" inert>
    <!-- Toast de notificação -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 999999 !important; pointer-events: auto;">
        <section id="loginToastOffcanvas" class="toast align-items-center border-0" role="status" aria-live="polite" aria-atomic="true" style="pointer-events: auto;">
            <div class="d-flex align-items-center">
                <div class="toast-body">
                    <i class="fa-solid fa-circle-info" style="color: #155BCB;" aria-hidden="true"></i>
                    <strong>Informação:</strong> 
                    <span>A função de acesso pelo gov.br está indisponível.</span>
                </div>
                <button type="button" class="btn-close me-2 m-auto shrink-0" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
        </section>
    </div>
    
    <div class="d-flex align-items-center justify-content-between p-3 pt-4">
		<?php
			if ($params->get('offcanvas_enable_logo', 0)) {
				echo $logo->renderFeature(false);
			} 
		?>
		<a href="#" class="close-offcanvas" role="button" aria-label="<?php echo Text::_('HELIX_ULTIMATE_CLOSE_OFFCANVAS_ARIA_LABEL'); ?>">
			<div class="burger-icon" aria-hidden="true">
				<span></span>
				<span></span>
				<span></span>
			</div>
		</a>
	</div>
	<div class="offcanvas-inner">
		<div class="d-flex header-modules p-3 mb-3 justify-content-center">
			<?php if ($params->get('offcanvas_enable_search', 0)): ?>
				<?php echo ModuleHelper::renderModule($searchModule, ['style' => 'sp_xhtml']); ?>
			<?php endif ?>

		<?php if ($params->get('offcanvas_enable_login', 0)): 
			//se nao tiver logado mostra botao de login
			$user = Factory::getUser();
			if ($user->id):
				$logoutUrl = Route::_('index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1');
				?>
				<a href="<?php echo $logoutUrl; ?>" class="btn-custom btn-primary btn-compact">
					<i class="text-white me-1 fa-solid fa-right-from-bracket"></i>
					<span class="ms-1 signin-text d-inline-block text-white"> Sair</span>
				</a>
			<?php else:
			?>
				<a href="#" 
					id="loginBtnOffcanvas"
					class="btn-custom btn-primary btn-compact">
					<i class="text-white me-1 fa-solid fa-user"></i>
					<span class="ms-1 signin-text d-inline-block text-white"> Entrar</span>
				</a>
			<?php endif; ?>
		<?php endif ?>
		</div>
		
		<?php if ($hasModMenu === false): ?>
			<div class="menu-wrapper">
				<?php echo ModuleHelper::renderModule($menuModule, ['style' => 'sp_xhtml']); ?>
			</div>
		<?php else: ?>
			<jdoc:include type="modules" name="offcanvas" style="sp_xhtml" />
		<?php endif ?>

		
		<?php if ($params->get('offcanvas_enable_contact') || $params->get('offcanvas_enable_social')): ?>
		<div class="mt-5">
		<?php if ($params->get('offcanvas_enable_contact')): ?>
			<div class="mb-4">
				<?php echo $contact->renderFeature(); ?>
			</div>
			<?php endif ?>
			
			<?php if ($params->get('offcanvas_enable_social')): ?>
				<div class="mb-4">
					<?php echo $social->renderFeature(); ?>
				</div>
			<?php endif ?>
		</div>
		<?php endif ?>
		
		<?php if ($hasModMenu === false): ?>
			<jdoc:include type="modules" name="offcanvas" style="sp_xhtml" />
		<?php endif ?>

		<!-- custom module position -->
		<jdoc:include type="modules" name="offcanvas-modules" style="sp_xhtml" />
	</div>
</div>