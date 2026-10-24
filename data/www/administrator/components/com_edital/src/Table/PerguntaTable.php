<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Table;
// No direct access
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Comedital\Component\Edital\Administrator\Helper\EditalHelper;
use \Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use \Joomla\CMS\Helper\ContentHelper;


/**
 * Pergunta table
 *
 * @since 1.0.0
 */
class PerguntaTable extends Table implements VersionableTableInterface, TaggableTableInterface
{
	use TaggableTableTrait;

	/**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;

	
	/**
	 * Constructor
	 *
	 * @param   JDatabase  &$db  A database connector object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_edital.pergunta';
		parent::__construct('#__tb_pergunta', 'id', $db);
		$this->setColumnAlias('published', 'state');
		
	}

	/**
	 * Get the type alias for the history table
	 *
	 * @return  string  The alias as described above
	 *
	 * @since   1.0.0
	 */
	public function getTypeAlias()
	{
		return $this->typeAlias;
	}

	/**
	 * Overloaded bind function to pre-process the params.
	 *
	 * @param   array  $array   Named array
	 * @param   mixed  $ignore  Optional array or list of parameters to ignore
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     Table:bind
	 * @since   1.0.0
	 * @throws  \InvalidArgumentException
	 */
	public function bind($array, $ignore = '')
	{
		$date = Factory::getDate();
		$task = Factory::getApplication()->input->get('task');
		$user = Factory::getApplication()->getIdentity();
		
		$input = Factory::getApplication()->input;
		$task = $input->getString('task', '');

		if ($array['id'] == 0 && empty($array['created_by']))
		{
			$array['created_by'] = Factory::getUser()->id;
		}

		if ($array['id'] == 0 && empty($array['modified_by']))
		{
			$array['modified_by'] = Factory::getUser()->id;
		}

		if ($task == 'apply' || $task == 'save')
		{
			$array['modified_by'] = Factory::getUser()->id;
		}

		// Support for multiple or not foreign key field: co_edital
			if(!empty($array['co_edital']))
			{
				if(is_array($array['co_edital'])){
					$array['co_edital'] = implode(',',$array['co_edital']);
				}
				else if(strrpos($array['co_edital'], ',') != false){
					$array['co_edital'] = explode(',',$array['co_edital']);
				}
			}
			else {
				$array['co_edital'] = 0;
			}

		// Support for multiple field: tp_pergunta
		if (isset($array['tp_pergunta']))
		{
			if (is_array($array['tp_pergunta']))
			{
				$array['tp_pergunta'] = implode(',',$array['tp_pergunta']);
			}
			elseif (strpos($array['tp_pergunta'], ',') != false)
			{
				$array['tp_pergunta'] = explode(',',$array['tp_pergunta']);
			}
			elseif (strlen($array['tp_pergunta']) == 0)
			{
				$array['tp_pergunta'] = '';
			}
		}
		else
		{
			$array['tp_pergunta'] = '';
		}

		// Support for multiple field: tp_requerida
		if (isset($array['tp_requerida']))
		{
			if (is_array($array['tp_requerida']))
			{
				$array['tp_requerida'] = implode(',',$array['tp_requerida']);
			}
			elseif (strpos($array['tp_requerida'], ',') != false)
			{
				$array['tp_requerida'] = explode(',',$array['tp_requerida']);
			}
			elseif (strlen($array['tp_requerida']) == 0)
			{
				$array['tp_requerida'] = '';
			}
		}
		else
		{
			$array['tp_requerida'] = '';
		}

		// Support for multiple field: tp_etapa
		if (isset($array['tp_etapa']))
		{
			if (is_array($array['tp_etapa']))
			{
				$array['tp_etapa'] = implode(',',$array['tp_etapa']);
			}
			elseif (strpos($array['tp_etapa'], ',') != false)
			{
				$array['tp_etapa'] = explode(',',$array['tp_etapa']);
			}
			elseif (strlen($array['tp_etapa']) == 0)
			{
				$array['tp_etapa'] = '';
			}
		}
		else
		{
			$array['tp_etapa'] = '';
		}

		if (isset($array['params']) && is_array($array['params']))
		{
			$registry = new Registry;
			$registry->loadArray($array['params']);
			$array['params'] = (string) $registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata']))
		{
			$registry = new Registry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}

		$this->normalizarDependencias($array);

		if (!empty($array['qt_dias_nao_aplica'])) {
			$array['qt_dias_complementacao'] = null;
		}

		unset($array['qt_dias_nao_aplica']);

		if (!$user->authorise('core.admin', 'com_edital.pergunta.' . $array['id']))
		{
			$actions         = Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/com_edital/access.xml',
				"/access/section[@name='pergunta']/"
			);
			$default_actions = Access::getAssetRules('com_edital.pergunta.' . $array['id'])->getData();
			$array_jaccess   = array();

			foreach ($actions as $action)
			{
				if (key_exists($action->name, $default_actions))
				{
					$array_jaccess[$action->name] = $default_actions[$action->name];
				}
			}

			$array['rules'] = $this->JAccessRulestoArray($array_jaccess);
		}

		// Bind the rules for ACL where supported.
		if (isset($array['rules']) && is_array($array['rules']))
		{
			$this->setRules($array['rules']);
		}

		return parent::bind($array, $ignore);
	}

	/**
	 * Method to store a row in the database from the Table instance properties.
	 *
	 * If a primary key value is set the row with that primary key value will be updated with the instance property values.
	 * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.0.0
	 */
	public function store($updateNulls = true)
	{
		
		
		return parent::store($updateNulls);
	}

	/**
	 * This function convert an array of Access objects into an rules array.
	 *
	 * @param   array  $jaccessrules  An array of Access objects.
	 *
	 * @return  array
	 */
	private function JAccessRulestoArray($jaccessrules)
	{
		$rules = array();

		foreach ($jaccessrules as $action => $jaccess)
		{
			$actions = array();

			if ($jaccess)
			{
				foreach ($jaccess->getData() as $group => $allow)
				{
					$actions[$group] = ((bool)$allow);
				}
			}

			$rules[$action] = $actions;
		}

		return $rules;
	}

	/**
	 * Normaliza a pergunta de origem vazia e a lista de valores da condição.
	 */
	protected function normalizarDependencias(array &$array): void
	{
		$pares = [
			'depende_de' => ['valor' => 'depende_de_valor', 'fonte' => 'depende_de_fonte'],
			'depende_de_ocultar' => ['valor' => 'depende_de_ocultar_valor', 'fonte' => 'depende_de_ocultar_fonte'],
		];

		foreach ($pares as $origem => $campos) {
			$campoValor = $campos['valor'];
			$fonte = $campos['fonte'];
			$temOrigem = array_key_exists($origem, $array);
			$bruto = $temOrigem ? trim((string) $array[$origem]) : '';
			$fonteInformada = array_key_exists($fonte, $array) ? (string) $array[$fonte] : '';
			$ehSolicitante = $bruto === PerguntaHelper::FONTE_SOLICITANTE
				|| ($bruto === '' && $fonteInformada === PerguntaHelper::FONTE_SOLICITANTE);

			if ($ehSolicitante) {
				$array[$origem] = null;
				$array[$fonte] = PerguntaHelper::FONTE_SOLICITANTE;
			} elseif ($temOrigem && $bruto !== '' && $bruto !== '0') {
				$array[$origem] = (int) $bruto;
				$array[$fonte] = null;
			} elseif ($temOrigem) {
				$array[$origem] = null;
				$array[$fonte] = null;
				$array[$campoValor] = null;
				continue;
			}

			if (!array_key_exists($campoValor, $array)) {
				continue;
			}

			if ($ehSolicitante) {
				$valores = PerguntaHelper::filtrarEsferasSolicitante($array[$campoValor]);
				$array[$campoValor] = $valores === [] ? null : json_encode($valores, JSON_UNESCAPED_UNICODE);
				continue;
			}

			if (array_key_exists($origem, $array) && empty($array[$origem])) {
				$array[$campoValor] = null;
				continue;
			}

			$array[$campoValor] = $this->serializarValoresDependencia($array[$campoValor]);
		}
	}

	protected function serializarValoresDependencia($valor): ?string
	{
		$valores = PerguntaHelper::valoresCondicao($valor);

		if ($valores === []) {
			return null;
		}

		if (is_string($valor)) {
			$texto = trim($valor);

			if ($texto !== '' && $texto[0] !== '[') {
				return $texto;
			}
		}

		return json_encode($valores, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Overloaded check function
	 *
	 * @return bool
	 */
	public function check()
	{
		// If there is an ordering column and this is a new row then get the next ordering value
		if (property_exists($this, 'ordering') && $this->id == 0)
		{
			$this->ordering = self::getNextOrder();
		}
		
		

		// Support for subform field ds_opcoes
		if (is_array($this->ds_opcoes))
		{
			$this->ds_opcoes = json_encode($this->ds_opcoes, JSON_UNESCAPED_UNICODE);
		}

		return parent::check();
	}

	/**
	 * Define a namespaced asset name for inclusion in the #__assets table
	 *
	 * @return string The asset name
	 *
	 * @see Table::_getAssetName
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;

		return $this->typeAlias . '.' . (int) $this->$k;
	}

	/**
	 * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
	 *
	 * @param   Table   $table  Table name
	 * @param   integer  $id     Id
	 *
	 * @see Table::_getAssetParentId
	 *
	 * @return mixed The id on success, false on failure.
	 */
	protected function _getAssetParentId($table = null, $id = null)
	{
		// We will retrieve the parent-asset from the Asset-table
		$assetParent = Table::getInstance('Asset');

		// Default: if no asset-parent can be found we take the global asset
		$assetParentId = $assetParent->getRootId();

		// The item has the component as asset-parent
		$assetParent->loadByName('com_edital');

		// Return the found asset-parent-id
		if ($assetParent->id)
		{
			$assetParentId = $assetParent->id;
		}

		return $assetParentId;
	}

	//XXX_CUSTOM_TABLE_FUNCTION

	
    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     */
    public function delete($pk = null)
    {
        $this->load($pk);
        $result = parent::delete($pk);
        
        return $result;
    }
}
