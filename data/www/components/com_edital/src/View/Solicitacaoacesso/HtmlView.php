<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Solicitacaoacesso;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Acesso ao Coleta MIR. Sem login: cria a conta (CPF, nome, e-mail, senha) e pede os perfis.
 * Com login ("Meus acessos"): acompanha os pedidos e pede outros perfis.
 *
 * @since  1.0.11
 */
class HtmlView extends BaseHtmlView
{
	/** Pedidos do usuário logado. */
	protected $solicitacoes = [];

	/** O usuário é do MIR (não precisa pedir esse perfil). */
	protected $ehMir = false;

	/** Dados digitados, devolvidos quando o cadastro falha. */
	protected $dados = [];

	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->guest)
		{
			$this->solicitacoes = AcessoHelper::getSolicitacoes((int) $user->id);
			$this->ehMir = AcessoHelper::ehMir($user);
		}

		$this->dados = (array) $app->getUserState('com_edital.solicitacaoacesso.dados', []);
		$app->setUserState('com_edital.solicitacaoacesso.dados', null);

		$this->document->setTitle($user->guest ? 'Solicitar acesso' : 'Meus acessos');

		parent::display($tpl);
	}
}
