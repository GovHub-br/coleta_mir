<?php

/**
 * @package Helix_Ultimate_Framework
 * @author JoomShaper <support@joomshaper.com>
 * @copyright Copyright (c) 2010 - 2025 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or Later
 */

defined('_JEXEC') or die('Restricted Access');

use HelixUltimate\Framework\Platform\Helper;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

$data               = $displayData;
$offcanvas_position = $data->params->get('offcanvas_position', 'right');
$menu_type          = $data->params->get('menu_type');

$feature_folder_path = JPATH_THEMES . '/' . $data->template->template . '/features';

include_once $feature_folder_path . '/social.php';
include_once $feature_folder_path . '/contact.php';
include_once $feature_folder_path . '/logo.php';
include_once $feature_folder_path . '/custom.php';

/**
 * Helper classes for-
 * social icons, contact info, site logo, Menu header.
 *
 */
$social 	= new HelixUltimateFeatureSocial($data->params);
$contact 	= new HelixUltimateFeatureContact($data->params);
$logo    	= new HelixUltimateFeatureLogo($data->params);
$menu    	= new HelixUltimateFeatureCustom($data->params);

/** Logo and menu html classes */
$logoClass = 'col-auto';
$menuClass = 'col-auto flex-auto';

/**
 * Get related modules
 * The modules are mod_search
 */
$searchModule       = Helper::getSearchModule('-header');
$oidcLoginHtml      = '';
$oidcLoginPublished = false;

foreach (ModuleHelper::getModules('login-modal-oidc') as $module) {
	if (($module->module ?? '') !== 'mod_oidc_login' || empty($module->id)) {
		continue;
	}

	$oidcLoginPublished = true;
	$oidcLoginHtml = trim(ModuleHelper::renderModule($module, ['style' => 'none']));
	break;
}

$sideClass       = ($offcanvas_position === 'left') ? 'offcanvas-toggler-left' : 'offcanvas-toggler-right';
$togglerHtml     = '
  <a id="offcanvas-toggler"
     class="offcanvas-toggler-secondary d-flex d-lg-none ' . $sideClass . ' align-items-center"
     href="#"
     aria-label="' . Text::_('HELIX_ULTIMATE_NAVIGATION') . '"
     title="' . Text::_('HELIX_ULTIMATE_NAVIGATION') . '">
     <div class="burger-icon"><span></span><span></span><span></span></div>
  </a>';
?>

<?php if ($data->params->get('sticky_header')): ?>
	<div class="sticky-header-placeholder"></div>
<?php endif; ?>

<div id="sp-top-bar-custom">
	<div class="container">
		<div class="container-inner">
			<div class="row">
				<div id="sp-top1" class="col-lg-6">
            <div class="sp-column text-center text-lg-start">
            <span class="fw-semibold">Portal do SINAPIR - Governo Federal</span>
						<?php if ($data->params->get('social_position') === 'top1'): ?>
							<?php echo $social->renderFeature(); ?>
						<?php endif ?>

						<?php if ($data->params->get('contact_position') === 'top1'): ?>
							<?php echo $contact->renderFeature(); ?>
						<?php endif ?>
						<jdoc:include type="modules" name="top1" style="sp_xhtml"/>
					</div>
				</div>

				<div id="sp-top2" class="col-lg-6">
					<div class="sp-column text-center text-lg-end">
            <span class="fw-semibold">Ministério da Igualdade Racial</span>
						<?php if ($data->params->get('social_position') === 'top2'): ?>
							<?php echo $social->renderFeature(); ?>
						<?php endif ?>

						<?php if ($data->params->get('contact_position') === 'top2'): ?>
							<?php echo $contact->renderFeature(); ?>
						<?php endif ?>
						<jdoc:include type="modules" name="top2" style="sp_xhtml" />
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<header id="sp-header">
	<div class="container">
		<div class="container-inner">
			<div class="row align-items-center">

				<!-- Left toggler if left/offcanvas -->
				<?php if ($offcanvas_position === 'left' ): ?>
					<div class="col-auto d-flex align-items-center">
						<?php echo $togglerHtml; ?>
					</div>
				<?php endif; ?>

				<!-- Logo -->
				<div id="sp-logo" class="<?php echo $logoClass; ?>">
					<div class="sp-column">
						<?php echo $logo->renderFeature(); ?>
						<jdoc:include type="modules" name="logo" style="sp_xhtml" />
					</div>
				</div>

				<!-- Menu -->
				<div id="sp-menu" class="<?php echo $menuClass; ?>">
					<div class="sp-column d-flex justify-content-end align-items-center">
						<?php echo $menu->renderFeature(); ?>
						<jdoc:include type="modules" name="menu" style="sp_xhtml" />

						<!-- Related Modules -->
						<div class="d-none d-lg-flex header-modules align-items-center">
							<?php if ($data->params->get('enable_search', 0)): ?>
								<?php echo ModuleHelper::renderModule($searchModule, ['style' => 'sp_xhtml']); ?>
							<?php endif ?>

							<?php if ($data->params->get('enable_login', 0)): ?>
								<?php echo $menu->renderLogin(); ?>
							<?php endif ?>
						</div>

						<!-- Right toggler  -->
						<?php if ($offcanvas_position === 'right'): ?>
							<?php echo $togglerHtml; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
  </header>


  <!-- Modal Login -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered login-modal-dialog">
      <div class="modal-content login-modal-content">

        <button type="button" class="btn-close login-modal-close" data-bs-dismiss="modal" aria-label="Fechar"></button>

        <!-- Cabeçalho -->
        <div class="login-modal-header">
          <div class="login-modal-icon">
            <i class="fa-solid fa-lock"></i>
          </div>
          <h5 id="loginModalLabel">Acesso à Plataforma</h5>
          <p>
            <?php if ($oidcLoginPublished): ?>
              Pessoas físicas e entes acessam com GOV.BR (vinculados na adesão).
            <?php else: ?>
              Pessoas físicas e entes acessam com CPF e senha cadastrados no momento da adesão.
            <?php endif; ?>
          </p>
        </div>

        <div class="login-modal-body">
          <?php if ($oidcLoginHtml !== ''): ?>
            <div class="login-govbr">
              <?php echo $oidcLoginHtml; ?>
            </div>
            <div class="login-divider"><span>ou</span></div>
          <?php endif; ?>

          <!-- Formulário CPF -->
          <form id="loginForm" class="login-cpf-form" action="<?php echo \Joomla\CMS\Router\Route::_('index.php', true); ?>" method="post">
            <!-- CPF -->
            <div class="login-field">
              <label for="loginCpf">CPF</label>
              <div class="login-input-wrapper">
                <i class="fa-solid fa-user login-input-icon" aria-hidden="true"></i>
                <input
                  id="loginCpf"
                  type="text"
                  name="username"
                  inputmode="numeric"
                  autocomplete="username"
                  placeholder="000.000.000-00"
                  maxlength="14"
                  required>
              </div>
            </div>

            <!-- Senha -->
            <div class="login-field">
              <label for="loginPassword">Senha</label>
              <div class="login-input-wrapper">
                <i class="fa-solid fa-lock login-input-icon" aria-hidden="true"></i>
                <input
                  id="loginPassword"
                  type="password"
                  name="password"
                  autocomplete="current-password"
                  placeholder="Sua senha"
                  required>
                <button type="button" class="login-toggle-password" aria-label="Mostrar senha">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>

            <!-- Botão Entrar -->
            <button type="submit" class="login-btn-submit">Entrar com CPF</button>

            <!-- Esqueceu a senha -->
            <div class="login-forgot">
              <a href="#" id="openResetModal">Esqueceu sua senha?</a>
            </div>

            <!-- Coleta MIR: cadastro com pedido de perfil -->
            <div class="login-forgot">
              Não tem conta? <a href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_edital&view=solicitacaoacesso'); ?>">Solicite acesso</a>
            </div>

            <!-- Termos -->
            <p class="login-terms">
              Ao acessar, você concorda com os Termos de Uso e Política de Privacidade
            </p>

            <!-- Campos ocultos do Joomla -->
            <input type="hidden" name="option" value="com_users">
            <input type="hidden" name="task" value="user.login">
            <input type="hidden" name="return" value="">
            <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          </form>
        </div>

      </div>
    </div>
  </div>

  <!-- Modal Alterar Senha -->
  <div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered login-modal-dialog">
      <div class="modal-content login-modal-content">

        <button type="button" class="btn-close login-modal-close" data-bs-dismiss="modal" aria-label="Fechar"></button>

        <!-- Cabeçalho -->
        <div class="login-modal-header">
          <div class="login-modal-icon">
            <i class="fa-solid fa-lock"></i>
          </div>
          <h5 id="resetModalLabel">Recuperar Senha</h5>
          <p>Informe seu email e enviaremos um link para redefinir sua senha</p>
        </div>

        <!-- Formulário -->
        <form id="resetForm" action="<?php echo \Joomla\CMS\Router\Route::_('index.php', true); ?>" method="post">
          <div class="login-modal-body">

            <!-- Email -->
            <div class="login-field">
              <label for="resetEmail">Email</label>
              <div class="login-input-wrapper">
                <i class="fa-solid fa-envelope login-input-icon" aria-hidden="true"></i>
                <input
                  id="resetEmail"
                  type="email"
                  name="jform[email]"
                  autocomplete="email"
                  placeholder="Digite seu email cadastrado"
                  required>
              </div>
              <?php
                    $config = Factory::getApplication()->getConfig();
                    $captchaPlugin = $config->get('captcha', '0');
                    
                    if ($captchaPlugin && $captchaPlugin !== '0') {
                        // Import captcha plugin
                        PluginHelper::importPlugin('captcha', $captchaPlugin);
                        
                        // Get captcha field
                        $app = Factory::getApplication();
                        $captchaFields = $app->triggerEvent('onInit', ['jform-captcha-recuperasenha']);
                        $captchaDisplay = $app->triggerEvent('onDisplay', ['jform[captcha]', 'jform-captcha-recuperasenha', 'form-control']);
                        
                        if (!empty($captchaDisplay[0])) :
                    ?>
                    <div class="captcha-section mb-4 text-center">
                        <!-- <h5><i class="fas fa-shield-alt"></i> Verificação de Segurança</h5> -->
                        <div class="captcha-container">
                            <?php echo $captchaDisplay[0]; ?>
                        </div>
                        <small class="form-text text-muted">Complete a verificação de segurança acima para continuar.</small>
                    </div>
                    <?php 
                        endif;
                    }
                    ?>
            </div>

            <!-- Botão Enviar -->
            <button type="submit" class="login-btn-submit">Enviar link de recuperação</button>
          </div>

          <!-- Campos ocultos -->
          <input type="hidden" name="option" value="com_users">
          <input type="hidden" name="task" value="reset.request">
          <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
        </form>

      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Abrir modal login ao clicar no botão Entrar
    const loginBtns = document.querySelectorAll('#loginBtn, #loginBtnOffcanvas');
    const loginModalEl = document.getElementById('loginModal');
    const resetModalEl = document.getElementById('resetModal');

    if (loginModalEl) {
      const loginModal = new bootstrap.Modal(loginModalEl);

      loginBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const offcanvas = document.querySelector('.offcanvas.show');
          if (offcanvas) {
            const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvas);
            if (bsOffcanvas) bsOffcanvas.hide();
          }
          $(".close-offcanvas").trigger('click');
          loginModal.show();
        });
      });

      // "Esqueceu sua senha?" → fechar login, abrir reset
      const openResetBtn = document.getElementById('openResetModal');
      if (openResetBtn && resetModalEl) {
        const resetModal = new bootstrap.Modal(resetModalEl);
        openResetBtn.addEventListener('click', (e) => {
          e.preventDefault();
          loginModal.hide();
          loginModalEl.addEventListener('hidden.bs.modal', function handler() {
            loginModalEl.removeEventListener('hidden.bs.modal', handler);
            resetModal.show();
          });
        });
      }
    }

    // Máscara de CPF (apenas no login)
    const loginCpfInput = document.getElementById('loginCpf');
    if (loginCpfInput) {
      loginCpfInput.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 11) v = v.slice(0, 11);
        v = v.replace(/^(\d{3})(\d)/, '$1.$2');
        v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
        e.target.value = v;
      });
    }

    // Antes de enviar o login, remove a pontuação do CPF
    const loginFormEl = document.getElementById('loginForm');
    if (loginFormEl) {
      loginFormEl.addEventListener('submit', () => {
        const cpfField = loginFormEl.querySelector('input[name="username"]');
        if (cpfField) {
          cpfField.value = cpfField.value.replace(/\D/g, '');
        }
      });
    }

    // Toggle visibilidade da senha (todos os botões)
    document.querySelectorAll('.login-toggle-password').forEach(toggleBtn => {
      toggleBtn.addEventListener('click', () => {
        const wrapper = toggleBtn.closest('.login-input-wrapper');
        const input = wrapper.querySelector('input[type="password"], input[type="text"]');
        const icon = toggleBtn.querySelector('i');
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
      });
    });
  });
  </script>
