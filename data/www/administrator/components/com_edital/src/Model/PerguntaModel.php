<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Model;
// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Event\Model;
use Joomla\CMS\Event\AbstractEvent;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;


/**
 * Pergunta model.
 *
 * @since  1.0.0
 */
class PerguntaModel extends AdminModel
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_EDITAL';

	/**
	 * @var    string  Alias to manage history control
	 *
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_edital.pergunta';

	/**
	 * @var    null  Item data
	 *
	 * @since  1.0.0
	 */
	protected $item = null;

	
	

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table    A database object
	 *
	 * @since   1.0.0
	 */
	public function getTable($type = 'Pergunta', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Marca ou desmarca perguntas como requeridas a partir da lista.
	 */
	public function setRequerida(array $pks, string $valor): bool
	{
		$valor = $valor === 'sim' ? 'sim' : 'nao';
		$user  = $this->getCurrentUser();

		if (!$user->authorise('core.edit', 'com_edital'))
		{
			$this->setError(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'));

			return false;
		}

		$pks = array_values(array_filter(array_map('intval', $pks)));

		if ($pks === [])
		{
			return true;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__tb_pergunta'))
			->set($db->quoteName('tp_requerida') . ' = ' . $db->quote($valor))
			->where($db->quoteName('id') . ' IN (' . implode(',', $pks) . ')')
			->where('(' . $db->quoteName('checked_out') . ' = 0 OR ' . $db->quoteName('checked_out') . ' IS NULL OR ' . $db->quoteName('checked_out') . ' = ' . (int) $user->id . ')');

		$db->setQuery($query);
		$db->execute();
		$this->cleanCache();

		return true;
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \JForm|boolean  A \JForm object on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Initialise variables.
		$app = Factory::getApplication();

		// Get the form.
		$form = $this->loadForm(
								'com_edital.pergunta', 
								'pergunta',
								array(
									'control' => 'jform',
									'load_data' => $loadData 
								)
							);

		

		if (empty($form))
		{
			return false;
		}

		$this->carregarCatalogoDependencia();

		return $form;
	}

	/**
	 * Opções das perguntas de lista, radio e checkbox para o campo de condição.
	 */
	protected function carregarCatalogoDependencia(): void
	{
		$document = Factory::getApplication()->getDocument();

		if (!$document || !method_exists($document, 'addScriptOptions'))
		{
			return;
		}

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'tp_pergunta', 'ds_opcoes']))
			->from($db->quoteName('#__tb_pergunta'))
			->where($db->quoteName('state') . ' != -2');
		$db->setQuery($query);

		$catalogo = [];
		$tiposLista = ['lista', 'radio', 'checkbox'];

		foreach ($db->loadObjectList() as $pergunta)
		{
			$catalogo[(string) $pergunta->id] = [
				'opcoes' => in_array($pergunta->tp_pergunta, $tiposLista, true)
					? PerguntaHelper::getOpcoesSelecao($pergunta)
					: [],
			];
		}

		$catalogo[PerguntaHelper::FONTE_SOLICITANTE] = [
			'opcoes' => PerguntaHelper::opcoesSolicitante(),
		];

		$document->addScriptOptions('com_edital.dependencia', [
			'catalogo'    => $catalogo,
			'adicionar'   => Text::_('COM_EDITAL_DEPENDENCIA_ADICIONAR_VALOR'),
			'remover'     => Text::_('COM_EDITAL_DEPENDENCIA_REMOVER_VALOR'),
			'selecione'   => Text::_('COM_EDITAL_DEPENDENCIA_SELECIONE'),
			'placeholder' => Text::_('COM_EDITAL_DEPENDENCIA_VALOR_PLACEHOLDER'),
		]);
	}

	

	/**
	 * O select grava o id da pergunta. Solicitante não tem id e volta como opção do próprio campo.
	 */
	protected function exporDependenciaSolicitante($data): void
	{
		if (!is_object($data))
		{
			return;
		}

		if (($data->depende_de_fonte ?? '') === PerguntaHelper::FONTE_SOLICITANTE)
		{
			$data->depende_de = PerguntaHelper::FONTE_SOLICITANTE;
		}

		if (($data->depende_de_ocultar_fonte ?? '') === PerguntaHelper::FONTE_SOLICITANTE)
		{
			$data->depende_de_ocultar = PerguntaHelper::FONTE_SOLICITANTE;
		}
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState($this->option . '.edit.' . $this->getName() . '.data', array());

		if (empty($data))
		{
			if ($this->item === null)
			{
				$this->item = $this->getItem();
			}

			$data = $this->item;
			$this->exporDependenciaSolicitante($data);

			// Support for multiple or not foreign key field: tp_pergunta
			$array = array();

			foreach ((array) $data->tp_pergunta as $value)
			{
				if (!is_array($value))
				{
					$array[] = $value;
				}
			}
			if(!empty($array)){

			$data->tp_pergunta = $array;
			}

			// Support for multiple or not foreign key field: tp_requerida
			$array = array();

			foreach ((array) $data->tp_requerida as $value)
			{
				if (!is_array($value))
				{
					$array[] = $value;
				}
			}
			if(!empty($array)){

			$data->tp_requerida = $array;
			}

			// Support for multiple or not foreign key field: tp_etapa
			$array = array();

			foreach ((array) $data->tp_etapa as $value)
			{
				if (!is_array($value))
				{
					$array[] = $value;
				}
			}
			if(!empty($array)){

			$data->tp_etapa = $array;
			}

			$dias = $data->qt_dias_complementacao ?? null;

			if ((int) ($data->id ?? 0) > 0 && property_exists($data, 'qt_dias_complementacao') && ($dias === null || $dias === ''))
			{
				$data->qt_dias_nao_aplica = 1;
				$data->qt_dias_complementacao = '';
			}
			else
			{
				$data->qt_dias_nao_aplica = 0;
			}
		}
		elseif (is_array($data) && !empty($data['qt_dias_nao_aplica']))
		{
			$data['qt_dias_complementacao'] = '';
		}

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		
			if ($item = parent::getItem($pk))
			{
				if (isset($item->params))
				{
					$item->params = json_encode($item->params);
				}
				
				// Do any procesing on fields here if needed
			}

			return $item;
		
	}

	/**
	 * Method to duplicate an Pergunta
	 *
	 * @param   array  &$pks  An array of primary key IDs.
	 *
	 * @return  boolean  True if successful.
	 *
	 * @throws  Exception
	 */
	public function duplicate(&$pks)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();
        $dispatcher = $this->getDispatcher();

		// Access checks.
		if (!$user->authorise('core.create', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
		}

		$context    = $this->option . '.' . $this->name;

		// Include the plugins for the save events.
		PluginHelper::importPlugin($this->events_map['save']);

		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			
				if ($table->load($pk, true))
				{
					// Reset the id to create a new record.
					$table->id = 0;

					if (!$table->check())
					{
						throw new \Exception($table->getError());
					}
					
				if (!empty($table->co_edital))
				{
					if (is_array($table->co_edital))
					{
						$table->co_edital = implode(',', $table->co_edital);
					}
				}
				else
				{
					$table->co_edital = '';
				}


					// Create the before save event.
					$beforeSaveEvent = AbstractEvent::create(
						$this->event_before_save,
						[
							'context' => $context,
							'subject' => $table,
							'isNew'   => true,
							'data'    => $table,
						]
					);

					// Trigger the before save event.
					$dispatchResult = Factory::getApplication()->getDispatcher()->dispatch($this->event_before_save, $beforeSaveEvent);

					// Check if dispatch result is an array and handle accordingly
					$result = isset($dispatchResult['result']) ? $dispatchResult['result'] : [];

					// Proceed with your logic
					if (in_array(false, $result, true) || !$table->store()) {
						throw new \Exception($table->getError());
					}

					// Trigger the after save event.
					Factory::getApplication()->getDispatcher()->dispatch(
						$this->event_after_save,
						AbstractEvent::create(
							$this->event_after_save,
							[
								'context'    => $context,
								'subject'    => $table,
								'isNew'      => true,
								'data'       => $table,
							]
						)
					);			
				}
				else
				{
					throw new \Exception($table->getError());
				}
			
		}

		// Clean cache
		$this->cleanCache();

		return true;
	}

	/**
	 * Prepare and sanitise the table prior to saving.
	 *
	 * @param   Table  $table  Table Object
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function prepareTable($table)
	{
		jimport('joomla.filter.output');

		if (empty($table->id))
		{
			// Set ordering to the last item if not set
			if (@$table->ordering === '')
			{
				$db = $this->getDbo();
				$db->setQuery('SELECT MAX(ordering) FROM #__tb_pergunta');
				$max             = $db->loadResult();
				$table->ordering = $max + 1;
			}
		}
	}

	/**
	 * @param   array  $data
	 *
	 * @return  boolean
	 */
	public function save($data)
	{
		$tiposComOpcoes = ['lista', 'radio', 'checkbox'];

		if (!empty($data['tp_pergunta']) && in_array($data['tp_pergunta'], $tiposComOpcoes, true))
		{
			$opcoes = $data['ds_opcoes'] ?? [];

			if (is_string($opcoes))
			{
				$opcoes = json_decode($opcoes, true) ?: [];
			}

			$opcoes = is_array($opcoes) ? array_filter($opcoes) : [];

			if ($opcoes === [])
			{
				$this->setError(Text::_('COM_EDITAL_ERROR_PERGUNTA_OPCOES_REQUIRED'));

				return false;
			}
		}

		if (!empty($data['co_agrupamento']) && !empty($data['co_edital']))
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName('co_editais'))
				->from($db->quoteName('#__tb_agrupamento'))
				->where($db->quoteName('id') . ' = ' . (int) $data['co_agrupamento']);
			$db->setQuery($query);
			$coEditais = (string) $db->loadResult();

			if ($coEditais !== '')
			{
				$ids = array_map('intval', explode(',', $coEditais));

				if (!in_array((int) $data['co_edital'], $ids, true))
				{
					$this->setError(Text::_('COM_EDITAL_ERROR_AGRUPAMENTO_EDITAL'));

					return false;
				}
			}
		}

		return parent::save($data);
	}
}
