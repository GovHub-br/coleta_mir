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
use \Joomla\CMS\Helper\ContentHelper;


/**
 * Adesao table
 *
 * @since 1.0.0
 */
class AdesaoTable extends Table implements VersionableTableInterface, TaggableTableInterface
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
		$this->typeAlias = 'com_edital.adesao';
		parent::__construct('#__tb_adesao', 'id', $db);
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

		// Origem só é definida pelo fluxo público, quando a adesão continua outra.
		// No administrador o valor postado é ignorado. 0 e vazio viram NULL por causa da FK.
		$adesaoId = (int) ($array['id'] ?? 0);

		if (Factory::getApplication()->isClient('administrator'))
		{
			unset($array['co_adesao_origem']);
		}
		else
		{
			$origem = (int) ($array['co_adesao_origem'] ?? 0);

			if ($origem > 0)
			{
				$array['co_adesao_origem'] = $origem;
			}
			elseif ($adesaoId > 0)
			{
				unset($array['co_adesao_origem']);
			}
			else
			{
				$array['co_adesao_origem'] = null;
			}
		}

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

		// Support for multiple field: tp_esfera_governamental
		if (isset($array['tp_esfera_governamental']))
		{
			if (is_array($array['tp_esfera_governamental']))
			{
				$array['tp_esfera_governamental'] = implode(',',$array['tp_esfera_governamental']);
			}
			elseif (strpos($array['tp_esfera_governamental'], ',') != false)
			{
				$array['tp_esfera_governamental'] = explode(',',$array['tp_esfera_governamental']);
			}
			elseif (strlen($array['tp_esfera_governamental']) == 0)
			{
				$array['tp_esfera_governamental'] = '';
			}
		}
		else
		{
			$array['tp_esfera_governamental'] = '';
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

		if ($array['id'] == 0)
		{
			$array['dt_criacao'] = $date->toSql();
		}

		// Support for multiple field: sg_estado
		if (isset($array['sg_estado']))
		{
			if (is_array($array['sg_estado']))
			{
				$array['sg_estado'] = implode(',',$array['sg_estado']);
			}
			elseif (strpos($array['sg_estado'], ',') != false)
			{
				$array['sg_estado'] = explode(',',$array['sg_estado']);
			}
			elseif (strlen($array['sg_estado']) == 0)
			{
				$array['sg_estado'] = '';
			}
		}
		else
		{
			$array['sg_estado'] = '';
		}

		// Support for multiple field: ds_municipios
		if (isset($array['ds_municipios']))
		{
			if (is_array($array['ds_municipios']))
			{
				$array['ds_municipios'] = implode(',',$array['ds_municipios']);
			}
			elseif (strpos($array['ds_municipios'], ',') != false)
			{
				$array['ds_municipios'] = explode(',',$array['ds_municipios']);
			}
			elseif (strlen($array['ds_municipios']) == 0)
			{
				$array['ds_municipios'] = '';
			}
		}
		else
		{
			$array['ds_municipios'] = '';
		}

		// Support for multiple field: st_adesao
		if (isset($array['st_adesao']))
		{
			if (is_array($array['st_adesao']))
			{
				$array['st_adesao'] = implode(',',$array['st_adesao']);
			}
			elseif (strpos($array['st_adesao'], ',') != false)
			{
				$array['st_adesao'] = explode(',',$array['st_adesao']);
			}
			elseif (strlen($array['st_adesao']) == 0)
			{
				$array['st_adesao'] = '';
			}
		}
		else
		{
			$array['st_adesao'] = '';
		}

		// Support for empty date field: dt_limite_situacao
		if (empty($array['dt_limite_situacao']) || $array['dt_limite_situacao'] == '0000-00-00')
		{
			$array['dt_limite_situacao'] = NULL;
			$this->dt_limite_situacao = NULL;
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

		if (!$user->authorise('core.admin', 'com_edital.adesao.' . $array['id']))
		{
			$actions         = Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/com_edital/access.xml',
				"/access/section[@name='adesao']/"
			);
			$default_actions = Access::getAssetRules('com_edital.adesao.' . $array['id'])->getData();
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

		if (empty($this->get('co_adesao_origem')))
		{
			$this->co_adesao_origem = null;
		}

		// A coluna só existe depois de rodar sql/14-adesao-tipo-solicitacao.sql
		if (property_exists($this, 'tp_solicitacao'))
		{
			$this->tp_solicitacao = $this->classificarTipoSolicitacao();
		}

		return parent::check();
	}

	/**
	 * Estado ou município que já aderiu ao SINAPIR em outro sistema (lista da página
	 * /contatos, em #__tb_ente_migracao) é "migracao"; o resto, inclusive consórcio,
	 * é "nova_adesao". Vale para qualquer status da adesão.
	 *
	 * @return string
	 */
	private function classificarTipoSolicitacao(): string
	{
		$esfera     = is_string($this->tp_esfera_governamental) ? $this->tp_esfera_governamental : '';
		$uf         = is_string($this->sg_estado) ? trim($this->sg_estado) : '';
		$municipios = is_array($this->ds_municipios) ? $this->ds_municipios : explode(',', (string) $this->ds_municipios);
		$municipios = array_values(array_filter(array_map('trim', $municipios), 'strlen'));

		if ($uf === '' || !in_array($esfera, ['estadual', 'municipal'], true))
		{
			return 'nova_adesao';
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__tb_ente_migracao'))
			->where($db->quoteName('tp_esfera') . ' = ' . $db->quote($esfera))
			->where($db->quoteName('sg_estado') . ' = ' . $db->quote($uf));

		if ($esfera === 'estadual')
		{
			$query->where($db->quoteName('co_ibge') . ' IS NULL');
		}
		elseif (count($municipios) === 1 && ctype_digit($municipios[0]))
		{
			$query->where($db->quoteName('co_ibge') . ' = ' . (int) $municipios[0]);
		}
		else
		{
			return 'nova_adesao';
		}

		$db->setQuery($query);

		return (int) $db->loadResult() > 0 ? 'migracao' : 'nova_adesao';
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
     * Apaga municípios e marcos temporais (sem tela no administrador) e depois o registro.
     * Respostas e adesões filhas permanecem e impedem a exclusão.
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return  boolean
     */
    public function delete($pk = null)
    {
        $this->load($pk);

        $id = (int) $this->id;

        if ($id > 0)
        {
            if (EditalHelper::countRelated('#__tb_resposta', 'co_adesao', $id) > 0)
            {
                $this->setError(Text::_('COM_EDITAL_ERROR_ADESAO_HAS_RESPOSTAS'));

                return false;
            }

            if (EditalHelper::countRelated('#__tb_adesao', 'co_adesao_origem', $id) > 0)
            {
                $this->setError(Text::_('COM_EDITAL_ERROR_ADESAO_HAS_FILHAS'));

                return false;
            }

            $db = $this->getDbo();

            foreach (['#__tb_municipio_adesao', '#__tb_marco_temporal'] as $related)
            {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($related))
                    ->where($db->quoteName('co_adesao') . ' = ' . $id);
                $db->setQuery($query);
                $db->execute();
            }
        }

        return parent::delete($pk);
    }
}
