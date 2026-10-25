<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Formularios;

defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;

/**
 * View class for a list of Formulario records.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $items;

	protected $pagination;

	protected $state;

	protected $params;

	protected $available;

	protected $closed;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  Template name
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		// if (empty($user->id))
		// {
		// 	$loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Route::_('index.php?option=com_edital&view=formularios', false)), false);
		// 	$app->redirect($loginUrl);

		// 	return;
		// }

		$this->state = $this->get('State');
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->params = $app->getParams('com_edital');
		
		// Group items into available and closed
		$this->available = array();
		$this->closed = array();
		
		$model = $this->getModel();
        $MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();
		
		if ($this->items)
		{
			foreach ($this->items as $item)
			{

				$adesoesDoEdital = $MinhaAdesaoModel->getAdesoesByUserIdEdital((int) $user->id, $item->id);
				$item->adesao = $adesoesDoEdital[0] ?? null;
				$item->tamanho = $model->getTamanhoFormulario($item->id);

				if (!empty($item->adesao) && !empty($item->adesao->id))
				{
					$item->progresso = $MinhaAdesaoModel->getProgressoPreenchimentoAdesao($item->adesao->id, $item->id);
				}
				else
				{
					$item->progresso = (object) [
						'respondidos' => 0,
						'total' => (int) ($item->tamanho['qtPerguntas'] ?? 0),
					];
				}

				if ($model->isAvailable($item) && (empty($item->adesao) || $item->adesao->st_adesao == 'rascunho'))
				{
					$this->available[] = $item;
				}
				else
				{
					$this->closed[] = $item;
				}
			}
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		// Breadcrumb: Início > Minha Adesão > Meus Formulários
		\Comedital\Component\Edital\Site\Helper\EditalHelper::addMinhaAdesaoPathway();


		parent::display($tpl);
	}
}
