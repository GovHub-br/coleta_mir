<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Inicio;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Página inicial do Coleta MIR: o que é, quem preenche o quê e como começar. Com login,
 * mostra a situação da pessoa (pedidos aguardando, UGs/CNPJs liberados e, para o MIR,
 * pedidos para decidir).
 *
 * @since  1.0.12
 */
class HtmlView extends BaseHtmlView
{
	protected $user;

	protected $ehMir = false;

	/** Pedidos do usuário aguardando o MIR. */
	protected $pendentes = [];

	/** UGs/CNPJs liberados: lista de ['perfil', 'codigo', 'nome']. */
	protected $unidades = [];

	/** Quantidade de instrumentos nas UGs/CNPJs liberados. */
	protected $totalInstrumentos = 0;

	/** Pedidos de todos aguardando decisão (só para o MIR). */
	protected $paraDecidir = 0;

	public function display($tpl = null)
	{
		$this->user = Factory::getApplication()->getIdentity();

		if (!$this->user->guest)
		{
			$userId = (int) $this->user->id;
			$this->ehMir = AcessoHelper::ehMir($this->user);
			$this->pendentes = array_values(array_filter(
				AcessoHelper::getSolicitacoes($userId),
				static fn ($s) => $s->st_solicitacao === 'pendente'
			));

			foreach (AcessoHelper::getUnidadesLiberadas($userId) as $perfil => $codigos)
			{
				foreach ($codigos as $codigo => $nome)
				{
					$this->unidades[] = ['perfil' => $perfil, 'codigo' => (string) $codigo, 'nome' => $nome];
				}
			}

			$this->totalInstrumentos = $this->unidades ? count(AcessoHelper::getInstrumentosDoGestor($userId)) : 0;
			$this->paraDecidir = $this->ehMir ? count(AcessoHelper::getParaDecisao('pendente')) : 0;
		}

		$this->document->setTitle('Coleta MIR');

		parent::display($tpl);
	}
}
