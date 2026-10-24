<?php

defined('_JEXEC') or die();

use HelixUltimate\Framework\Core\Classes\HelixultimateMenu;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\ModuleHelper;

require_once __DIR__ . '/menu.php';

class HelixUltimateFeatureCustom extends HelixUltimateFeatureMenu
{
  public function __construct($params)
  {
    parent::__construct($params);
    $this->position = 'menu';
  }
  /**
   * Render login/sign in option in header
   *
   * @return	string	The login HTML string.
   * @since	2.0.0
   */
  public function renderLogin()
  {
    $user = Factory::getApplication()->getIdentity();

    $html = [];
    $html[] = '<div class="sp-module">';

    if ($user->id === 0) {
      $html[] = '<a href="#"
        id="loginBtn"
        class="btn-custom btn-primary btn-compact"
        data-bs-toggle="modal"
        data-bs-target="#loginModal">
        <i class="me-1 fa-solid fa-user"></i>
        <span class="ms-1 signin-text d-none d-lg-inline-block"> Entrar</span>
        </a>';
    } else {
      $html[] = '<div class="sp-profile-wrapper">';
      $html[] = '<a href="#" class="sp-sign-in"><i class="fas fa-user-circle" aria-hidden="true"></i> <span class="user-text d-none d-xl-inline-block"> ' . ($user->name ?? '') . '</span> <i class="fas fa-chevron-down arrow-icon" aria-hidden="true"></i></a>';
      $html[] = '<ul class="sp-profile-dropdown">';

      $modules= ModuleHelper::getModules('logged-in-usermenu');

      if (!empty($modules)) {
        $html[] = '<li class="custom_user_login_menu">'.ModuleHelper::renderModule($modules[0], ['style' => 'none']).'</li>';
      }

      $html[] = '	<li class="sp-profile-dropdown-item">';
      $html[] = '		<a href="' . Route::_('index.php?option=com_users&view=profile') . '">' . Text::_('HELIX_ULTIMATE_USER_PROFILE') . '</a>';
      $html[] = '	</li>';
      $logoutReturn = base64_encode(Uri::root());
      $logoutUrl = Route::_('index.php?option=com_users&task=user.logout&return=' . $logoutReturn . '&' . Session::getFormToken() . '=1');
      $html[] = '	<li class="sp-profile-dropdown-item">';
      $html[] = '		<a href="' . $logoutUrl . '">' . Text::_('HELIX_ULTIMATE_USER_LOGOUT') . '</a>';
      $html[] = '	</li>';
      $html[] = '</ul>';
      $html[] = '</div>';
    }

    $html[] = '</div>';

    return implode("\n", $html);
  }
}
