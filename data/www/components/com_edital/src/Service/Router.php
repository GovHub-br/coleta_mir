<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Service;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Class EditalRouter
 *
 */
class Router extends RouterView
{
	private $noIDs;
	/**
	 * The category factory
	 *
	 * @var    CategoryFactoryInterface
	 *
	 * @since  1.0.0
	 */
	private $categoryFactory;

	/**
	 * The category cache
	 *
	 * @var    array
	 *
	 * @since  1.0.0
	 */
	private $categoryCache = [];

	public function __construct(SiteApplication $app, AbstractMenu $menu, CategoryFactoryInterface $categoryFactory, DatabaseInterface $db)
	{
		$params = ComponentHelper::getParams('com_edital');
		$this->noIDs = (bool) $params->get('sef_ids');
		$this->categoryFactory = $categoryFactory;
		
		
			$editais = new RouterViewConfiguration('editais');
			$this->registerView($editais);
			$ccEdital = new RouterViewConfiguration('edital');
			$ccEdital->setKey('id')->setParent($editais);
			$this->registerView($ccEdital);
			$adesoes = new RouterViewConfiguration('adesoes');
			$this->registerView($adesoes);
			$ccAdesao = new RouterViewConfiguration('adesao');
			$ccAdesao->setKey('id')->setParent($adesoes);
			$this->registerView($ccAdesao);
			$adesaoform = new RouterViewConfiguration('adesaoform');
			$adesaoform->setKey('id');
			$this->registerView($adesaoform);
			$adesoespreenchimento = new RouterViewConfiguration('adesoespreenchimento');
			$this->registerView($adesoespreenchimento);
			$formularios = new RouterViewConfiguration('formularios');
			$this->registerView($formularios);
			$formulariogestao = new RouterViewConfiguration('formulariogestao');
			$this->registerView($formulariogestao);
			// Respostas dos entes de um formulário: fica sob o item de menu "Gestão de Formulários"
			$formularioadesoes = new RouterViewConfiguration('formularioadesoes');
			$formularioadesoes->setParent($formulariogestao);
			$this->registerView($formularioadesoes);
			$construtorlista = new RouterViewConfiguration('construtorlista');
			$this->registerView($construtorlista);
			// Seções e perguntas: fica sob o item de menu "Construtor de Formulários"
			$construtorperguntas = new RouterViewConfiguration('construtorperguntas');
			$construtorperguntas->setParent($construtorlista);
			$this->registerView($construtorperguntas);
			$formulariorespostas = new RouterViewConfiguration('formulariosrespostas');
			$formulariorespostas->setKey('id');
			$this->registerView($formulariorespostas);
			$cadastroeditais = new RouterViewConfiguration('cadastroeditais');
			$this->registerView($cadastroeditais);
			$editalform = new RouterViewConfiguration('editalform');
			$editalform->setParent($cadastroeditais);
			$this->registerView($editalform);

			$cadastroagrupamentos = new RouterViewConfiguration('cadastroagrupamentos');
			$this->registerView($cadastroagrupamentos);
			$agrupamentoform = new RouterViewConfiguration('agrupamentoform');
			$agrupamentoform->setParent($cadastroagrupamentos);
			$this->registerView($agrupamentoform);

			$cadastroperguntas = new RouterViewConfiguration('cadastroperguntas');
			$this->registerView($cadastroperguntas);
			$perguntaform = new RouterViewConfiguration('perguntaform');
			$perguntaform->setParent($cadastroperguntas);
			$this->registerView($perguntaform);

			$minhaadesao = new RouterViewConfiguration('minhaadesao');
			$this->registerView($minhaadesao);

			$adesaogestao = new RouterViewConfiguration('adesaogestao');
			$this->registerView($adesaogestao);

			// Acesso ao Coleta MIR: cadastro e pedidos de perfil; decisão pelo MIR.
			$solicitacaoacesso = new RouterViewConfiguration('solicitacaoacesso');
			$this->registerView($solicitacaoacesso);
			$solicitacoesacesso = new RouterViewConfiguration('solicitacoesacesso');
			$this->registerView($solicitacoesacesso);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}


	
		/**
		 * Method to get the segment(s) for an edital
		 *
		 * @param   string  $id     ID of the edital to retrieve the segments for
		 * @param   array   $query  The request that is built right now
		 *
		 * @return  array|string  The segments of this item
		 */
		public function getEditalSegment($id, $query)
		{
			return array((int) $id => $id);
		}
		/**
		 * Method to get the segment(s) for an adesao
		 *
		 * @param   string  $id     ID of the adesao to retrieve the segments for
		 * @param   array   $query  The request that is built right now
		 *
		 * @return  array|string  The segments of this item
		 */
		public function getAdesaoSegment($id, $query)
		{
			return array((int) $id => $id);
		}
			/**
			 * Method to get the segment(s) for an adesaoform
			 *
			 * @param   string  $id     ID of the adesaoform to retrieve the segments for
			 * @param   array   $query  The request that is built right now
			 *
			 * @return  array|string  The segments of this item
			 */
			public function getAdesaoformSegment($id, $query)
			{
				return $this->getAdesaoSegment($id, $query);
			}

	
		/**
		 * Method to get the segment(s) for an edital
		 *
		 * @param   string  $segment  Segment of the edital to retrieve the ID for
		 * @param   array   $query    The request that is parsed right now
		 *
		 * @return  mixed   The id of this item or false
		 */
		public function getEditalId($segment, $query)
		{
			return (int) $segment;
		}
		/**
		 * Method to get the segment(s) for an adesao
		 *
		 * @param   string  $segment  Segment of the adesao to retrieve the ID for
		 * @param   array   $query    The request that is parsed right now
		 *
		 * @return  mixed   The id of this item or false
		 */
		public function getAdesaoId($segment, $query)
		{
			return (int) $segment;
		}
			/**
			 * Method to get the segment(s) for an adesaoform
			 *
			 * @param   string  $segment  Segment of the adesaoform to retrieve the ID for
			 * @param   array   $query    The request that is parsed right now
			 *
			 * @return  mixed   The id of this item or false
			 */
			public function getAdesaoformId($segment, $query)
			{
				return $this->getAdesaoId($segment, $query);
			}

		/**
		 * @param   string  $id
		 * @param   array   $query
		 *
		 * @return  array
		 */
		public function getEditalformSegment($id, $query)
		{
			return array((int) $id => $id);
		}

		/**
		 * @param   string  $id
		 * @param   array   $query
		 *
		 * @return  array
		 */
		public function getAgrupamentoformSegment($id, $query)
		{
			return array((int) $id => $id);
		}

		/**
		 * @param   string  $id
		 * @param   array   $query
		 *
		 * @return  array
		 */
		public function getPerguntaformSegment($id, $query)
		{
			return array((int) $id => $id);
		}

		/**
		 * @param   string  $segment
		 * @param   array   $query
		 *
		 * @return  int
		 */
		public function getEditalformId($segment, $query)
		{
			return (int) $segment;
		}

		/**
		 * @param   string  $segment
		 * @param   array   $query
		 *
		 * @return  int
		 */
		public function getAgrupamentoformId($segment, $query)
		{
			return (int) $segment;
		}

		/**
		 * @param   string  $segment
		 * @param   array   $query
		 *
		 * @return  int
		 */
		public function getPerguntaformId($segment, $query)
		{
			return (int) $segment;
		}

	/**
	 * Method to get categories from cache
	 *
	 * @param   array  $options   The options for retrieving categories
	 *
	 * @return  CategoryInterface  The object containing categories
	 *
	 * @since   1.0.0
	 */
	private function getCategories(array $options = []): CategoryInterface
	{
		$key = serialize($options);

		if (!isset($this->categoryCache[$key]))
		{
			$this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
		}

		return $this->categoryCache[$key];
	}
}
