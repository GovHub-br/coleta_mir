<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Solicitacoesacesso;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Pedidos de acesso para o MIR liberar ou recusar.
 *
 * @since  1.0.11
 */
class HtmlView extends BaseHtmlView
{
	protected $pendentes = [];

	protected $decididas = [];

	public function display($tpl = null)
	{
		if (!AcessoHelper::ehMir(Factory::getApplication()->getIdentity()))
		{
			throw new \Exception('Sem permissão.', 403);
		}

		$this->pendentes = AcessoHelper::getParaDecisao('pendente');
		$this->decididas = array_slice(array_filter(AcessoHelper::getParaDecisao(''), static fn ($s) => $s->st_solicitacao !== 'pendente'), 0, 50);
		$this->document->setTitle('Solicitações de acesso');

		parent::display($tpl);
	}
}
